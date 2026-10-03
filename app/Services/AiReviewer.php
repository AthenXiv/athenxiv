<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Http;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;

/**
 * AI pre-review.
 *
 * Speaks the OpenAI `/chat/completions` dialect, which means it also works with
 * DeepSeek, Moonshot, Qwen, Together, Groq, OpenRouter, vLLM, Ollama and any
 * other compatible gateway — the administrator only has to fill in a base URL,
 * a key and a model name.
 *
 * The reviewer is deliberately asked for a strict JSON verdict so a failure to
 * comply degrades into "needs human review" rather than a wrong decision.
 */
final class AiReviewer
{
    public const MODE_OFF = 'off';
    public const MODE_SEMI = 'semi';
    public const MODE_AUTO = 'auto';

    public const DECISION_APPROVE = 'approve';
    public const DECISION_REJECT  = 'reject';
    public const DECISION_REVIEW  = 'review';

    /** @return array{enabled:bool,mode:string,reason:string} */
    public static function status(): array
    {
        $mode = (string) Settings::get('ai.mode', self::MODE_OFF);
        if (!Settings::bool('ai.enabled')) {
            return ['enabled' => false, 'mode' => self::MODE_OFF, 'reason' => 'disabled'];
        }
        if (!in_array($mode, [self::MODE_SEMI, self::MODE_AUTO], true)) {
            return ['enabled' => false, 'mode' => self::MODE_OFF, 'reason' => 'mode is off'];
        }
        if (trim((string) Settings::get('ai.api_key', '')) === '') {
            return ['enabled' => false, 'mode' => $mode, 'reason' => 'no API key'];
        }
        if (trim((string) Settings::get('ai.base_url', '')) === '') {
            return ['enabled' => false, 'mode' => $mode, 'reason' => 'no base URL'];
        }
        if (!Http::tlsAvailable() && !str_starts_with((string) Settings::get('ai.base_url'), 'http://')) {
            return ['enabled' => false, 'mode' => $mode, 'reason' => 'no CA bundle for TLS'];
        }
        return ['enabled' => true, 'mode' => $mode, 'reason' => 'ok'];
    }

    public static function enabled(): bool
    {
        return self::status()['enabled'];
    }

    public static function mode(): string
    {
        return self::status()['enabled'] ? (string) Settings::get('ai.mode') : self::MODE_OFF;
    }

    /**
     * Review one paper. Never throws: every failure is returned so the caller
     * can leave the paper in the human queue.
     *
     * @param array<string,mixed> $paper
     * @return array{ok:bool,decision:string,confidence:int,reason:string,tags:string[],
     *               category_slug:?string,section_slug:?string,model:string,raw:string,error:?string,
     *               input_chars:int,pdf_text:bool,prompt_tokens?:int,completion_tokens?:int}
     */
    public static function review(array $paper, ?string $pdfPath = null): array
    {
        $status = self::status();
        if (!$status['enabled']) {
            return self::failure('AI review unavailable: ' . $status['reason']);
        }

        $pdfText = '';
        $usedPdf = false;
        $maxInput = max(2000, Settings::int('ai.max_input_chars', 12000));
        if ($pdfPath !== null && Settings::bool('ai.read_pdf')) {
            $pdfText = PdfText::extractQuietly($pdfPath, (int) ($maxInput * 0.75));
            $usedPdf = $pdfText !== '';
        }

        [$system, $user] = self::buildPrompt($paper, $pdfText, $maxInput);
        $payload = [
            'model'       => (string) Settings::get('ai.model', 'gpt-4o-mini'),
            'temperature' => (float) Settings::get('ai.temperature', 0),
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        $url = rtrim((string) Settings::get('ai.base_url'), '/') . '/chat/completions';
        $response = Http::post(
            $url,
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . (string) Settings::get('ai.api_key'),
                'Accept'        => 'application/json',
            ],
            ['timeout' => max(10, Settings::int('ai.timeout', 90)), 'follow' => false]
        );

        if (!$response['ok'] || $response['body'] === '') {
            Logger::warning('AI review transport failure', [
                'paper_id' => $paper['id'] ?? null,
                'status'   => $response['status'],
                'error'    => $response['error'],
            ]);
            return self::failure('request failed: ' . (string) ($response['error'] ?? 'unknown'), $usedPdf, mb_strlen($pdfText));
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return self::failure('response was not JSON', $usedPdf, mb_strlen($pdfText), $response['body']);
        }
        if (isset($decoded['error'])) {
            $message = is_array($decoded['error'])
                ? (string) ($decoded['error']['message'] ?? json_encode($decoded['error']))
                : (string) $decoded['error'];
            return self::failure('provider error: ' . $message, $usedPdf, mb_strlen($pdfText), $response['body']);
        }

        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        $verdict = self::parseVerdict($content);
        if ($verdict === null) {
            return self::failure('model did not return a usable JSON verdict', $usedPdf, mb_strlen($pdfText), $content);
        }

        $usage = is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [];
        return [
            'ok'            => true,
            'decision'      => $verdict['decision'],
            'confidence'    => $verdict['confidence'],
            'reason'        => $verdict['reason'],
            'tags'          => $verdict['tags'],
            'category_slug' => $verdict['category_slug'],
            'category_new'  => $verdict['category_new'],
            'section_slug'  => $verdict['section_slug'],
            'model'         => (string) ($decoded['model'] ?? Settings::get('ai.model')),
            'raw'           => $content,
            'error'         => null,
            'input_chars'   => $verdict['_input_chars'],
            'pdf_text'      => $usedPdf,
            'prompt_tokens' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            'completion_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
        ];
    }

    /**
     * Subject areas to show the model, most promising first.
     *
     * The taxonomy has ~600 nodes. Handing the model "the first 220 in tree
     * order" hides whole disciplines (everything after the first branch), which
     * is why papers landed in "Other" and new areas were invented next to an
     * existing one. Score the areas against the submission instead: keep every
     * root, the whole subtree of the best-matching discipline, every area whose
     * name shares a word with the title/abstract/keywords, and finally fill up
     * to the cap so the model still sees the breadth of the taxonomy.
     *
     * @return string[]
     */
    public static function areaCandidates(array $paper, int $limit = 260): array
    {
        $all = Category::flat(false);
        if ($all === []) {
            return [];
        }

        $line = static fn (array $category): string => str_repeat('  ', (int) ($category['depth'] ?? 0))
            . $category['slug'] . ' (' . Category::name($category, 'en') . ')';

        $haystack = mb_strtolower(implode(' ', [
            (string) ($paper['title'] ?? ''),
            (string) ($paper['subtitle'] ?? ''),
            (string) ($paper['abstract'] ?? ''),
            (string) ($paper['keywords'] ?? ''),
            (string) ($paper['category_other'] ?? ''),
        ]));

        // Whole words plus their stems: "neuroscience" should also match
        // "neurosciences", "photonic" should match "photonics".
        $stems = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $haystack) ?: [] as $word) {
            if (mb_strlen($word) >= 5) {
                $stems[$word] = true;
                $stems[mb_substr($word, 0, max(5, mb_strlen($word) - 2))] = true;
            }
        }
        $stems = array_keys($stems);

        $byId = [];
        foreach ($all as $category) {
            $byId[(int) $category['id']] = $category;
        }
        $rootOf = static function (int $id) use ($byId): int {
            $guard = 0;
            while ((int) ($byId[$id]['parent_id'] ?? 0) > 0
                && isset($byId[(int) $byId[$id]['parent_id']])
                && $guard++ < 12) {
                $id = (int) $byId[$id]['parent_id'];
            }
            return $id;
        };

        $scores = [];
        $rootTotals = [];
        foreach ($all as $category) {
            $id = (int) $category['id'];
            $name = mb_strtolower(Category::name($category, 'en') . ' ' . (string) $category['slug']);
            $points = 0;
            foreach ($stems as $stem) {
                if ($stem !== '' && str_contains($name, $stem)) {
                    $points++;
                }
            }
            $scores[$id] = $points;
            if ($points > 0) {
                $root = $rootOf($id);
                $rootTotals[$root] = ($rootTotals[$root] ?? 0) + $points;
            }
        }
        arsort($rootTotals);
        $bestRoot = (int) (array_key_first($rootTotals) ?? 0);

        $chosen = [];
        $order = [];
        $take = static function (array $category) use (&$chosen, &$order): void {
            $id = (int) $category['id'];
            if (!isset($chosen[$id])) {
                $chosen[$id] = true;
                $order[] = $category;
            }
        };

        foreach ($all as $category) {                       // every top-level discipline
            if ((int) ($category['parent_id'] ?? 0) === 0) {
                $take($category);
            }
        }
        if ($bestRoot > 0) {                                // the likely discipline, in full
            foreach ($all as $category) {
                if ($rootOf((int) $category['id']) === $bestRoot) {
                    $take($category);
                }
            }
        }
        foreach ($all as $category) {                       // anything matching a word
            if (($scores[(int) $category['id']] ?? 0) > 0) {
                $take($category);
            }
        }
        foreach ($all as $category) {                       // breadth, up to the cap
            if (count($order) >= $limit) {
                break;
            }
            $take($category);
        }

        return array_map($line, array_slice($order, 0, $limit));
    }

    /** @return array{0:string,1:string} [system, user] */
    public static function buildPrompt(array $paper, string $pdfText, int $maxInput): array
    {        $custom = trim((string) Settings::get('ai.system_prompt', ''));
        $system = $custom !== '' ? $custom : self::defaultSystemPrompt();

        $extra = trim((string) Settings::get('ai.rubric_extra', ''));
        if ($extra !== '') {
            $system .= "\n\n" . $extra;
        }

        $sections = [];
        foreach (Section::ordered(false) as $section) {
            $sections[] = $section['slug'] . ' (' . Section::name($section, 'en') . ')';
        }
        $categories = self::areaCandidates($paper, 260);

        $system .= "\n\nAvailable sections (choose one slug):\n- " . implode("\n- ", $sections);
        $system .= "\n\nAvailable subject areas (choose one slug, prefer the most specific):\n" . implode("\n", $categories);
        $system .= "\n\nAnswer with a single JSON object and nothing else:\n"
            . "{\n"
            . "  \"decision\": \"approve\" | \"reject\" | \"review\",\n"
            . "  \"confidence\": 0-100,\n"
            . "  \"reason\": \"two to four sentences addressed to the author, written in the language named by the LANGUAGE field (use English only if that field is missing or unreadable)\",\n"
            . "  \"category_slug\": \"one slug from the list, or null\",\n"
            . "  \"section_slug\": \"one slug from the list, or null\",\n"
            . "  \"category_new\": {\"slug\": \"lower-case-slug\", \"name\": \"Area name\", \"name_zh\": \"中文名\", \"parent_slug\": \"one of the listed slugs or null\"},\n"
            . "  \"tags\": [\"up to six keywords\"]\n"
            . "}";
        $system .= "\n\nSet \"category_new\" to null whenever one of the listed areas fits. Only when no listed area "
            . "can reasonably hold the paper should you propose a new one: a broad, reusable discipline name "
            . "(not a phrase describing this single paper), with a kebab-case slug, and parent_slug pointing at the "
            . "closest listed area (or null if it deserves to be a top-level area).";
        $system .= "\n\nClassifying the paper is part of your job, not an optional extra: always return the best "
            . "fitting \"category_slug\" and \"section_slug\" you can justify. If the author named an area that is not "
            . "on the list, map it to the closest listed area instead of copying their wording. Only if nothing on "
            . "the list is even approximately right, describe one new broad area in \"category_new\".";

        $authors = [];
        foreach (Paper::authors((int) ($paper['id'] ?? 0)) as $author) {
            $authors[] = $author['name'] . (!empty($author['affiliation']) ? ' (' . $author['affiliation'] . ')' : '');
        }

        // The verdict is read by the author, so it must be written in the
        // language they submitted in. Give the model both the endonym (so it
        // recognises the language) and the English name (so it can follow the
        // instruction reliably), and never leave it guessing.
        $languageCode = (string) ($paper['language'] ?? 'en');
        $languageLabel = \Athenaeum\Core\Languages::label($languageCode, $paper['language_custom'] ?? null);
        $languageName = \Athenaeum\Core\Languages::isCustom($languageCode)
            ? $languageLabel
            : \Athenaeum\Core\Languages::englishName($languageCode);
        if (trim($languageName) === '') {
            $languageName = 'English';
        }
        $languageLabel = trim($languageLabel) === '' ? $languageName : $languageLabel;
        if ($languageLabel !== $languageName) {
            $languageLabel .= ' / ' . $languageName;
        } elseif ($languageCode !== '' && !str_contains($languageLabel, $languageCode)) {
            $languageLabel .= ' (' . $languageCode . ')';
        }

        $body = "TITLE: " . ($paper['title'] ?? '') . "\n"
            . (!empty($paper['subtitle']) ? 'SUBTITLE: ' . $paper['subtitle'] . "\n" : '')
            . 'AUTHORS: ' . (implode('; ', $authors) ?: 'not listed') . "\n"
            . 'LANGUAGE: ' . $languageLabel . "\n"
            . (!empty($paper['keywords']) ? 'KEYWORDS: ' . $paper['keywords'] . "\n" : '')
            . (!empty($paper['doi']) ? 'DOI: ' . $paper['doi'] . "\n" : '')
            . (!empty($paper['category_other'])
                ? 'AUTHOR SAYS THE SUBJECT AREA IS (it was not in our list): ' . $paper['category_other'] . "\n"
                : '')
            . "\nThe author reads " . $languageName . ": write the \"reason\" field in " . $languageName . ".\n"
            . "\nABSTRACT:\n" . trim((string) ($paper['abstract'] ?? '')) . "\n";

        if ($pdfText !== '') {
            $body .= "\nEXTRACTED PDF TEXT (may be incomplete or noisy):\n" . $pdfText . "\n";
        } else {
            $body .= "\n(No machine-readable PDF text was available; judge from the metadata and abstract alone and lower your confidence accordingly.)\n";
        }

        if (mb_strlen($body) > $maxInput) {
            $body = mb_substr($body, 0, $maxInput) . "\n…[truncated]";
        }

        return [$system, $body];
    }

    public static function defaultSystemPrompt(): string
    {
        return <<<'TXT'
You are the pre-screening assistant of "AthenXiv", an open, multilingual archive for research in every academic field — the natural sciences, mathematics, engineering, medicine, the social sciences and the humanities alike. Its editorial posture is explicitly:

* OPEN: anyone may submit — no degree, position, affiliation or sponsor is required. Independent researchers and non-academic scholars are welcome. Low legibility, weak English, unusual formatting or a heterodox thesis are NOT grounds for rejection by themselves.
* HONEST: pseudoscience and academic fraud are refused. That means: claims that overturn well-established science while offering no testable evidence; fabricated data, figures or citations; plagiarism or laundry of generated text into false authorship; impersonation or fabricated affiliations; content with no scholarly substance at all (advertising, harassment, pure ranting).
* EPISTEMICALLY CAREFUL: a timestamp proves a file existed at a time; it never certifies that the contents are correct. Do not treat "has a proof" as evidence of quality, and do not invent facts about the submission that you cannot see.

Your job:
1. Decide whether the submission should be published as-is ("approve"), refused ("reject"), or sent to a human editor ("review").
2. Choose the best-fitting section slug and subject-area slug from the lists you are given.
3. Write a short, respectful reason addressed to the author, in the submission's own language (the LANGUAGE field). If you reject, name the concrete problem and, where possible, what would make the work acceptable. Never accuse the author of fraud unless the text itself contains clear evidence (e.g. fabricated references that contradict each other); if you merely suspect it, use "review".
4. Use "review" whenever you are unsure, when the text is too short or too damaged to judge, when the topic falls outside your competence, or when the decision would hinge on facts you cannot verify. A human editor will then decide.

Judge the work, not the author's identity, institution or language.
TXT;
    }

    /**
     * Lenient JSON extraction: models sometimes wrap the object in prose or
     * fences, and some providers ignore `response_format`.
     *
     * @return array{decision:string,confidence:int,reason:string,tags:string[],category_slug:?string,section_slug:?string,_input_chars:int}|null
     */
    public static function parseVerdict(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }
        $json = $content;
        if (!str_starts_with($json, '{')) {
            $start = strpos($json, '{');
            $end = strrpos($json, '}');
            if ($start === false || $end === false || $end <= $start) {
                return null;
            }
            $json = substr($json, $start, $end - $start + 1);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            // Tolerate trailing commas, a common model slip.
            $repaired = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
            $decoded = json_decode($repaired, true);
        }
        if (!is_array($decoded)) {
            return null;
        }

        $decision = strtolower(trim((string) ($decoded['decision'] ?? '')));
        if (!in_array($decision, [self::DECISION_APPROVE, self::DECISION_REJECT, self::DECISION_REVIEW], true)) {
            $decision = self::DECISION_REVIEW;
        }

        $confidence = (int) ($decoded['confidence'] ?? 0);
        $confidence = max(0, min(100, $confidence));

        $tags = [];
        foreach ((array) ($decoded['tags'] ?? []) as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = mb_substr(trim($tag), 0, 40);
            }
        }

        $slug = static function (mixed $value): ?string {
            if (!is_string($value)) {
                return null;
            }
            $value = trim($value);
            return $value === '' || strtolower($value) === 'null' ? null : $value;
        };

        // A proposed new area is only accepted when it carries a usable name.
        $categoryNew = null;
        $proposed = $decoded['category_new'] ?? null;
        if (is_array($proposed)) {
            $name = trim((string) ($proposed['name'] ?? $proposed['name_en'] ?? ''));
            $proposedSlug = \Athenaeum\Core\Str::slug((string) ($proposed['slug'] ?? $name), 60);
            if ($name !== '' && $proposedSlug !== '') {
                $categoryNew = [
                    'slug'        => $proposedSlug,
                    'name'        => mb_substr($name, 0, 120),
                    'name_zh'     => mb_substr(trim((string) ($proposed['name_zh'] ?? '')), 0, 120),
                    'parent_slug' => $slug($proposed['parent_slug'] ?? null),
                ];
            }
        }

        return [
            'decision'      => $decision,
            'confidence'    => $confidence,
            'reason'        => mb_substr(trim((string) ($decoded['reason'] ?? '')), 0, 2000),
            'tags'          => array_slice($tags, 0, 6),
            'category_slug' => $slug($decoded['category_slug'] ?? null),
            'category_new'  => $categoryNew,
            'section_slug'  => $slug($decoded['section_slug'] ?? null),
            '_input_chars'  => 0,
        ];
    }

    /** @return array<string,mixed> */
    private static function failure(string $error, bool $usedPdf = false, int $chars = 0, string $raw = ''): array
    {
        return [
            'ok'            => false,
            'decision'      => self::DECISION_REVIEW,
            'confidence'    => 0,
            'reason'        => '',
            'tags'          => [],
            'category_slug' => null,
            'category_new'  => null,
            'section_slug'  => null,
            'model'         => (string) Settings::get('ai.model', ''),
            'raw'           => mb_substr($raw, 0, 4000),
            'error'         => $error,
            'input_chars'   => $chars,
            'pdf_text'      => $usedPdf,
        ];
    }

    /** Cheap connectivity probe used by the admin panel. */
    public static function probe(): array
    {
        $base = rtrim((string) Settings::get('ai.base_url'), '/');
        if ($base === '') {
            return ['ok' => false, 'error' => 'no base URL'];
        }
        $response = Http::get($base . '/models', [
            'Authorization' => 'Bearer ' . (string) Settings::get('ai.api_key'),
            'Accept'        => 'application/json',
        ], ['timeout' => 15, 'follow' => false]);

        if ($response['status'] === 401) {
            return ['ok' => false, 'error' => 'unauthorised (check the API key)', 'status' => 401];
        }
        if (!$response['ok']) {
            return ['ok' => false, 'error' => (string) ($response['error'] ?? 'unreachable'), 'status' => $response['status']];
        }
        $decoded = json_decode($response['body'], true);
        $models = [];
        foreach ((array) ($decoded['data'] ?? []) as $model) {
            if (isset($model['id'])) {
                $models[] = (string) $model['id'];
            }
        }
        sort($models);
        return ['ok' => true, 'models' => array_slice($models, 0, 200), 'status' => $response['status']];
    }
}
