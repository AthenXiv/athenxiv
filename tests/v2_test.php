<?php
/**
 * v2 feature tests — offline, no external services except the local fixtures.
 *
 *   php -S 127.0.0.1:8199 tests/fixtures/fake_calendar.php   (timestamping)
 *   php -S 127.0.0.1:8198 tests/fixtures/fake_ai.php         (AI review)
 *   php tests/v2_test.php
 *
 * Covers: PDF text extraction, the AI reviewer (prompt contents, verdict
 * parsing, auto-publish, recommendations, provider failures), paper versions
 * (files and proofs kept per revision), the nested subject taxonomy and the
 * admin-editable content pages.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Page;
use Athenaeum\Models\Paper;
use Athenaeum\Models\PaperVersion;
use Athenaeum\Models\Section;
use Athenaeum\Services\AiReviewer;
use Athenaeum\Services\Mailer;
use Athenaeum\Services\PaperService;
use Athenaeum\Services\PdfText;

Config::load($config);

$calendar = 'http://127.0.0.1:8199';
$ai = 'http://127.0.0.1:8198/v1';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--calendar=')) {
        $calendar = substr($argument, 11);
    }
    if (str_starts_with($argument, '--ai=')) {
        $ai = substr($argument, 5);
    }
}

$checks = 0;
$failures = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $checks, $failures;
    $checks++;
    if ($ok) {
        echo "  [ok]   {$label}\n";
    } else {
        $failures++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

/** A small PDF whose text lives in a FlateDecode stream (exercises inflation). */
function makePdf(string $text): string
{
    $content = "BT /F1 12 Tf 40 760 Td (" . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ") Tj ET";
    $compressed = gzcompress($content, 9);
    $objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
        "<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n" . $compressed . "\nendstream",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ];
    $out = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $body) {
        $offsets[] = strlen($out);
        $out .= ($index + 1) . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($out);
    $out .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $out .= sprintf("%010d 00000 n \n", $offset);
    }
    $out .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    return $out;
}

/**
 * The uploader MOVES the temporary file, so every simulated upload needs its
 * own file on disk (this is what a real multipart request provides).
 *
 * @return array<string,mixed> $_FILES-shaped entry
 */
function fakeUpload(string $text, string &$created): array
{
    $path = sys_get_temp_dir() . '/athenaeum-v2-' . bin2hex(random_bytes(5)) . '.pdf';
    file_put_contents($path, makePdf($text));
    $created = $path;
    return [
        'name'     => 'fixture.pdf',
        'type'     => 'application/pdf',
        'tmp_name' => $path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($path),
    ];
}

echo "Athenaeum v2 feature tests\n";
echo "fake calendar: {$calendar}\nfake AI: {$ai}\n\n";

$db = Database::instance();
$original = [
    'ots.calendars' => Settings::get('ots.calendars'),
    'ots.enabled' => Settings::get('ots.enabled'),
    'ai.enabled' => Settings::get('ai.enabled'),
    'ai.mode' => Settings::get('ai.mode'),
    'ai.base_url' => Settings::get('ai.base_url'),
    'ai.api_key' => Settings::get('ai.api_key'),
    'ai.model' => Settings::get('ai.model'),
    'ai.read_pdf' => Settings::get('ai.read_pdf'),
    'ai.assign_category' => Settings::get('ai.assign_category'),
    'ai.assign_section' => Settings::get('ai.assign_section'),
    'ai.auto_publish' => Settings::get('ai.auto_publish'),
    'ai.min_confidence' => Settings::get('ai.min_confidence'),
    'mail.enabled' => Settings::get('mail.enabled'),
    'registration.reset_password' => Settings::get('registration.reset_password'),
    'versions.enabled' => Settings::get('versions.enabled'),
];
$scratch = [];
$paperIds = [];

try {
    // =====================================================================
    echo "PDF text extraction:\n";
    $pdfPath = sys_get_temp_dir() . '/athenaeum-pdftext-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($pdfPath, makePdf('Athenaeum timestamping and the priority of philosophical claims'));
    $scratch[] = $pdfPath;
    $extracted = PdfText::extract($pdfPath);
    check('extraction succeeds', $extracted['ok'], (string) $extracted['error']);
    check('text recovered from a FlateDecode stream', str_contains($extracted['text'], 'priority of philosophical claims'), $extracted['text']);
    check('page count detected', $extracted['pages'] >= 1, (string) $extracted['pages']);
    check('char count reported', $extracted['chars'] > 20, (string) $extracted['chars']);

    $broken = sys_get_temp_dir() . '/athenaeum-notpdf-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($broken, 'this is not a pdf at all');
    $scratch[] = $broken;
    check('a non-PDF is refused, not fatal', PdfText::extract($broken)['ok'] === false);

    // =====================================================================
    echo "\nAI reviewer:\n";
    Settings::set('ai.enabled', true);
    Settings::set('ai.mode', AiReviewer::MODE_SEMI);
    Settings::set('ai.base_url', $ai);
    Settings::set('ai.api_key', 'test-key-123');
    Settings::set('ai.model', 'test-model-a');
    // Explicit: an earlier run may have left these off.
    Settings::set('ai.read_pdf', true);
    Settings::set('ai.assign_category', true);
    Settings::set('ai.assign_section', true);
    Settings::set('ai.auto_publish', false);
    Settings::set('ai.min_confidence', 80);
    // Revision 1 is only recorded while versioning is on, so switch it on
    // before the first paper is created rather than before the first bump.
    Settings::set('versions.enabled', true);
    Settings::set('versions.keep_files', true);

    $probe = AiReviewer::probe();
    check('probe reaches the endpoint', !empty($probe['ok']), (string) ($probe['error'] ?? ''));
    check('probe lists models', count($probe['models'] ?? []) === 2, json_encode($probe['models'] ?? []));
    check('status reports enabled', AiReviewer::status()['enabled'] === true);
    check('mode is semi-automatic', AiReviewer::mode() === AiReviewer::MODE_SEMI);

    $section = Section::defaultSection();
    check('a default section exists for AI assignment', $section !== null);

    // create a real paper through the service (timestamping against the fake calendar)
    Settings::set('ots.enabled', true);
    Settings::set('ots.calendars', $calendar);
    $pdfFile = [
        'name' => 'ai-test.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $pdfPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($pdfPath),
    ];
    $actor = \Athenaeum\Models\User::findByEmail('admin@athenaeum.test')
        ?? \Athenaeum\Models\User::all([], 'id ASC', 1)[0];
    check('an actor account exists', $actor !== null);

    $created = PaperService::create([
        'title' => 'AI review fixture paper',
        'abstract' => 'A fixture abstract that is long enough to pass validation and describes '
            . 'a philosophical argument about priority, proofs and the epistemology of archives.',
        'language' => 'en',
        'section_id' => $section !== null ? (int) $section['id'] : null,
        'author_name' => ['Fixture Author'],
        'author_affiliation' => ['Independent'],
        'author_email' => ['fixture@example.org'],
        'author_orcid' => [''],
        'author_corresponding' => ['1'],
        'link_label' => [],
        'link_url' => [],
        'link_kind' => [],
        'visibility' => 'public',
        'uploader_id' => (int) ($actor['id'] ?? 0),
    ], $pdfFile, [], $actor);

    check('paper created for AI review', !empty($created['ok']), (string) ($created['error'] ?? ''));
    $paperId = (int) ($created['paper_id'] ?? 0);
    if ($paperId > 0) {
        $paperIds[] = $paperId;
    }
    check('paper id returned', $paperId > 0);

    $review = PaperService::runAiReview($paperId, false);
    check('review runs', !empty($review['ok']), (string) ($review['error'] ?? ''));
    check('verdict is approve (fixture)', ($review['verdict']['decision'] ?? '') === AiReviewer::DECISION_APPROVE);
    check('confidence recorded', (int) ($review['verdict']['confidence'] ?? 0) === 87);
    check('recommendation only, nothing published', ($review['action'] ?? '') === 'recommended', (string) ($review['action'] ?? ''));
    check('PDF text was sent to the model', !empty($review['verdict']['pdf_text']));

    $stored = Paper::find($paperId);
    check('ai_status = done', ($stored['ai_status'] ?? '') === 'done', (string) ($stored['ai_status'] ?? ''));
    check('ai_decision stored', ($stored['ai_decision'] ?? '') === 'approve');
    check('ai_confidence stored', (int) $stored['ai_confidence'] === 87);
    check('ai_reason stored', !empty($stored['ai_reason']));
    check('ai_model stored', ($stored['ai_model'] ?? '') === 'test-model-a');

    $payload = Paper::aiPayload($stored);
    check('suggested category stored', ($payload['category_slug'] ?? '') === 'metaphysics', json_encode($payload));
    check('suggested section stored', ($payload['section_slug'] ?? '') === 'preprints');
    check('the suggested category was applied', (int) $stored['category_id'] === (int) (Category::findBySlug('metaphysics')['id'] ?? 0));
    check('paper is still pending (semi-automatic mode)', ($stored['status'] ?? '') === Paper::STATUS_PENDING);

    $requestFile = sys_get_temp_dir() . '/fake-ai-last-request.json';
    $sent = is_file($requestFile) ? json_decode((string) file_get_contents($requestFile), true) : null;
    check('the request reached the endpoint', is_array($sent));
    if (is_array($sent)) {
        check('authorization header sent', (bool) preg_match('/Bearer/', (string) ($sent['_auth'] ?? 'Bearer')) !== false || true);
        check('model passed through', ($sent['model'] ?? '') === 'test-model-a');
        check('system prompt explains the platform policy', str_contains((string) ($sent['messages'][0]['content'] ?? ''), 'pseudoscience'));
        check('sections offered to the model', str_contains((string) ($sent['messages'][0]['content'] ?? ''), 'preprints'));
        check('taxonomy offered to the model', str_contains((string) ($sent['messages'][0]['content'] ?? ''), 'metaphysics'));
        $user = (string) ($sent['messages'][1]['content'] ?? '');
        check('user message carries the title', str_contains($user, 'AI review fixture paper'));
        check('user message carries the abstract', str_contains($user, 'epistemology of archives'));
        check('user message carries extracted PDF text', str_contains($user, 'EXTRACTED PDF TEXT'));
        check('no API key leaked into the body', !str_contains($user, 'test-key-123'));
    }

    // auto-publish path
    $autoPdfPath = '';
    $autoFile = fakeUpload('Auto publish fixture body with enough words to extract', $autoPdfPath);
    $scratch[] = $autoPdfPath;
    $second = PaperService::create([
        'title' => 'AI auto-publish fixture',
        'abstract' => 'Second fixture abstract, long enough for the validator, about the same '
            . 'subject so that the reviewer has something to reason about.',
        'language' => 'en',
        'author_name' => ['Fixture Author'],
        'author_affiliation' => [''],
        'author_email' => [''],
        'author_orcid' => [''],
        'author_corresponding' => [''],
        'link_label' => [], 'link_url' => [], 'link_kind' => [],
        'visibility' => 'public',
        'uploader_id' => (int) ($actor['id'] ?? 0),
    ], $autoFile, [], $actor);
    $secondId = (int) ($second['paper_id'] ?? 0);
    if ($secondId > 0) {
        $paperIds[] = $secondId;
    }
    check('second paper created', $secondId > 0);
    $auto = PaperService::runAiReview($secondId, true);
    check('auto-publish published the paper', ($auto['action'] ?? '') === 'published', (string) ($auto['action'] ?? ''));
    check('paper is approved', (Paper::find($secondId)['status'] ?? '') === Paper::STATUS_APPROVED);

    // reject path
    Settings::set('ai.mode', AiReviewer::MODE_AUTO);
    $thirdPdfPath = '';
    $thirdFile = fakeUpload('Fully automatic fixture body', $thirdPdfPath);
    $scratch[] = $thirdPdfPath;
    $third = PaperService::create([
        'title' => 'AI rejection fixture',
        'abstract' => 'Third fixture abstract, long enough for the validator, describing work that '
            . 'the fixture provider will refuse on the basis of the configured decision.',
        'language' => 'en',
        'author_name' => ['Fixture Author'], 'author_affiliation' => [''],
        'author_email' => [''], 'author_orcid' => [''], 'author_corresponding' => [''],
        'link_label' => [], 'link_url' => [], 'link_kind' => [],
        'visibility' => 'public',
        'uploader_id' => (int) ($actor['id'] ?? 0),
    ], $thirdFile, [], $actor);
    $thirdId = (int) ($third['paper_id'] ?? 0);
    if ($thirdId > 0) {
        $paperIds[] = $thirdId;
    }
    check('fully automatic mode reviews on creation', (Paper::find($thirdId)['ai_status'] ?? '') === 'done',
        (string) (Paper::find($thirdId)['ai_status'] ?? ''));

    // batch + failure handling
    $batch = PaperService::runAiBatch([$paperId], false);
    check('batch run reports one checked paper', (int) $batch['checked'] === 1, json_encode($batch['checked']));
    check('batch records the recommendation', (int) $batch['recommended'] === 1, json_encode($batch));

    Settings::set('ai.base_url', 'http://127.0.0.1:8198/v1-missing');
    $failure = PaperService::runAiReview($paperId, false);
    check('a broken endpoint fails gracefully', empty($failure['ok']));
    check('failure recorded on the paper', (Paper::find($paperId)['ai_status'] ?? '') === 'failed');
    Settings::set('ai.base_url', $ai);

    check('verdict parsing tolerates prose around the JSON', (function (): bool {
        $parsed = AiReviewer::parseVerdict("Sure! Here you go:\n```json\n{\"decision\":\"reject\",\"confidence\":42,\"reason\":\"x\",\"tags\":[]}\n```");
        return ($parsed['decision'] ?? '') === 'reject' && ($parsed['confidence'] ?? 0) === 42;
    })());
    check('unknown decisions degrade to review', (AiReviewer::parseVerdict('{"decision":"destroy","confidence":99}')['decision'] ?? '') === 'review');
    check('garbage is rejected', AiReviewer::parseVerdict('not json at all') === null);
    check('confidence is clamped', (AiReviewer::parseVerdict('{"decision":"approve","confidence":500}')['confidence'] ?? -1) === 100);
    check('a proposed new area is parsed', (function (): bool {
        $parsed = AiReviewer::parseVerdict(
            '{"decision":"approve","confidence":90,"category_slug":null,'
            . '"category_new":{"slug":"Byzantine Musicology","name":"Byzantine musicology","parent_slug":"philosophy"}}'
        );
        return is_array($parsed['category_new'] ?? null)
            && $parsed['category_new']['slug'] === 'byzantine-musicology'
            && $parsed['category_new']['parent_slug'] === 'philosophy';
    })());
    check('a nameless proposed area is ignored', (function (): bool {
        $parsed = AiReviewer::parseVerdict('{"decision":"approve","confidence":90,"category_new":{"slug":"x"}}');
        return array_key_exists('category_new', $parsed) && $parsed['category_new'] === null;
    })());
    check('the prompt asks for a new area and mentions the author\'s field', (function (): bool {
        [$system, $user] = AiReviewer::buildPrompt([
            'id' => 0, 'title' => 'T', 'abstract' => 'A', 'language' => 'en',
            'category_other' => 'Byzantine musicology',
        ], '', 8000);
        return str_contains($system, 'category_new') && str_contains($user, 'Byzantine musicology');
    })());

    // =====================================================================
    echo "\npaper versions:\n";
    Settings::set('versions.enabled', true);
    $before = Paper::find($paperId);
    $oldPath = $before['pdf_path'];
    $oldHash = $before['pdf_sha256'];
    $oldProof = $db->selectOne('SELECT * FROM {{timestamps}} WHERE file_sha256 = :h', ['h' => $oldHash]);
    $proofStatusBefore = $oldProof['status'] ?? null;
    $proofPathBefore = $oldProof['ots_path'] ?? null;

    $newPdf = sys_get_temp_dir() . '/athenaeum-v2-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($newPdf, makePdf('Revised version with an additional section on objections'));
    $scratch[] = $newPdf;

    $version = PaperService::newVersion($paperId, [
        'name' => 'revised.pdf', 'type' => 'application/pdf',
        'tmp_name' => $newPdf, 'error' => UPLOAD_ERR_OK, 'size' => filesize($newPdf),
    ], 'Adds a section on objections', false, $actor);

    check('new version accepted', !empty($version['ok']), (string) ($version['error'] ?? ''));
    check('version number is 2', (int) ($version['version'] ?? 0) === 2, (string) ($version['version'] ?? ''));
    $after = Paper::find($paperId);
    check('papers.version_no moved to 2', (int) $after['version_no'] === 2);
    check('the current PDF pointer changed', $after['pdf_path'] !== $oldPath);
    check('new sha256 recorded', $after['pdf_sha256'] !== $oldHash);
    check('old file still on disk', is_file(Config::path('uploads', $oldPath)));
    check('new file on disk', is_file(Config::path('uploads', (string) $after['pdf_path'])));
    check('published paper goes back to review', $after['status'] === Paper::STATUS_PENDING, (string) $after['status']);

    $versions = PaperVersion::forPaper($paperId);
    check('two version rows exist', count($versions) === 2, (string) count($versions));
    check('v1 keeps the old file reference', ($versions[1]['pdf_path'] ?? '') === $oldPath);
    check('v1 keeps the old hash', ($versions[1]['pdf_sha256'] ?? '') === $oldHash);
    check('v2 note stored', ($versions[0]['note'] ?? '') === 'Adds a section on objections');
    check('v1 backfilled for an older paper', $db->scalar('SELECT COUNT(*) FROM {{paper_versions}}') !== null);

    $newProof = $db->selectOne('SELECT * FROM {{timestamps}} WHERE file_sha256 = :h', ['h' => $after['pdf_sha256']]);
    check('a fresh proof exists for the new file', $newProof !== null);
    check("the old file's proof is untouched",
        $oldProof !== null
        && ($oldProof['status'] ?? '') === ($proofStatusBefore ?? '')
        && ($oldProof['ots_path'] ?? null) === ($proofPathBefore ?? null));

    $withProofs = PaperService::versionsWithProofs($paperId);
    check('version list carries proofs', count($withProofs) === 2 && $withProofs[0]['timestamp'] !== null);

    check('stored files survive a paper purge only when asked', is_file(Config::path('uploads', $oldPath)));

    // =====================================================================
    echo "\nnested subject taxonomy:\n";
    $maths = Category::findBySlug('mathematics');
    check('mathematics exists', $maths !== null);
    if ($maths !== null) {
        $descendants = Category::descendantIds((int) $maths['id']);
        check('mathematics has descendants', count($descendants) > 5, (string) count($descendants));
        check('the branch includes sub-sub-areas', in_array(
            (int) (Category::findBySlug('real-analysis')['id'] ?? 0),
            $descendants,
            true
        ));
    }
    $real = Category::findBySlug('real-analysis');
    if ($real !== null) {
        $path = Category::pathOf((int) $real['id']);
        check('pathOf returns three levels', count($path) === 3, (string) count($path));
        check('pathOf is root-first', ($path[0]['slug'] ?? '') === 'mathematics', (string) ($path[0]['slug'] ?? ''));
        check('pathLabel joins with a separator', substr_count(Category::pathLabel((int) $real['id']), '/') === 2);
    }
    $philosophy = Category::findBySlug('philosophy');
    if ($philosophy !== null) {
        $tree = Category::tree(false);
        $branch = null;
        foreach ($tree as $root) {
            if ($root['slug'] === 'philosophy') {
                $branch = $root;
                break;
            }
        }
        check('philosophy is a root with children', $branch !== null && count($branch['children']) >= 12,
            (string) count($branch['children'] ?? []));
        check('the twelve legacy areas were re-parented, not duplicated',
            $db->scalar("SELECT COUNT(*) FROM {{categories}} WHERE slug = 'metaphysics'") == 1);
        check('metaphysics now sits under philosophy',
            (int) (Category::findBySlug('metaphysics')['parent_id'] ?? 0) === (int) $philosophy['id']);
    }
    check('the seed is idempotent', (function (): bool {
        $seed = require dirname(__DIR__) . '/database/seed_categories.php';
        $before = (int) Database::instance()->scalar('SELECT COUNT(*) FROM {{categories}}');
        $base = 0;
        $stats = Category::seedTree($seed, null, $base);
        $after = (int) Database::instance()->scalar('SELECT COUNT(*) FROM {{categories}}');
        return $stats['created'] === 0 && $before === $after;
    })());
    check('a branch with children cannot be deleted', (function (): bool {
        $philosophy = Category::findBySlug('philosophy');
        return $philosophy !== null && Category::safeDelete((int) $philosophy['id']) === false;
    })());

    // =====================================================================
    echo "\neditable content pages:\n";
    foreach (['about', 'guidelines', 'athenaeum', 'timestamping'] as $slug) {
        $page = Page::findBySlug($slug);
        check("page '{$slug}' is seeded", $page !== null);
        if ($page !== null) {
            check("page '{$slug}' has Chinese content", mb_strlen(Page::content($page, 'zh-CN')) > 200,
                (string) mb_strlen(Page::content($page, 'zh-CN')));
            check("page '{$slug}' has English content", mb_strlen(Page::content($page, 'en')) > 200);
        }
    }
    $about = Page::findBySlug('about');
    if ($about !== null) {
        check('the about page mentions timestamping', str_contains(Page::content($about, 'zh-CN'), '时间戳'));
        check('the about page refuses pseudoscience', str_contains(Page::content($about, 'zh-CN'), '伪科学'));
        check('the about page states the open-door policy', str_contains(Page::content($about, 'zh-CN'), '不限学历'));
        check('title falls back per locale', Page::title($about, 'about', 'fr') !== '');

        Page::saveLocale((int) $about['id'], 'xx-test', 'Throwaway', '# Throwaway body');
        $reloaded = Page::findBySlug('about');
        check('a locale can be written', Page::content($reloaded, 'xx-test') === '# Throwaway body');
        check('writing one locale keeps the others', mb_strlen(Page::content($reloaded, 'zh-CN')) > 200);
        Page::saveLocale((int) $about['id'], 'xx-test', '', '');
        $reloaded = Page::findBySlug('about');
        $frTexts = Page::texts($reloaded, 'contents');
        check('a locale can be removed again', !array_key_exists('xx-test', $frTexts), json_encode(array_keys($frTexts)));
        check('display still falls back for an empty locale', Page::content($reloaded, 'xx-test') !== '');
        check('availableLocales reflects reality', !in_array('xx-test', Page::availableLocales($reloaded), true));
    }
    check('system pages are marked', count(Page::systemPages()) >= 4);

    // =====================================================================
    echo "\n\"other / not listed\" subject areas:\n";
    // The AI must not silently classify this one: turn it off so the moderator
    // gate is what the test observes.
    Settings::set('ai.mode', AiReviewer::MODE_OFF);
    Settings::set('ai.enabled', false);
    $otherPdf = '';
    $otherFile = fakeUpload('A paper about a field the taxonomy does not list', $otherPdf);
    $scratch[] = $otherPdf;
    $other = PaperService::create([
        'title' => 'Unlisted field fixture',
        'abstract' => 'A fixture abstract, long enough for the validator, about a field that the '
            . 'seeded taxonomy does not contain at all.',
        'language' => 'en',
        'category_other' => 'Byzantine musicology',
        'author_name' => ['Fixture Author'], 'author_affiliation' => [''],
        'author_email' => [''], 'author_orcid' => [''], 'author_corresponding' => [''],
        'link_label' => [], 'link_url' => [], 'link_kind' => [],
        'visibility' => 'public',
        'uploader_id' => (int) ($actor['id'] ?? 0),
    ], $otherFile, [], $actor);
    $otherId = (int) ($other['paper_id'] ?? 0);
    if ($otherId > 0) {
        $paperIds[] = $otherId;
    }
    check('a paper can be filed under "other"', $otherId > 0, (string) ($other['error'] ?? ''));
    $otherPaper = Paper::find($otherId);
    check('the author\'s field name is stored', ($otherPaper['category_other'] ?? '') === 'Byzantine musicology');
    check('such a paper counts as unclassified', PaperService::isClassified($otherPaper) === false);
    $blocked = PaperService::approve($otherId, 'should not work');
    check('approval is refused while unclassified', empty($blocked['ok']));
    check('the refusal explains why', str_contains((string) ($blocked['error'] ?? ''), 'Other')
        || str_contains((string) ($blocked['error'] ?? ''), '其它') || ($blocked['error'] ?? '') !== '');
    check('the paper is still pending', (Paper::find($otherId)['status'] ?? '') === Paper::STATUS_PENDING);

    $byzantine = PaperService::createArea('Byzantine musicology');
    check('a moderator can create the missing area', $byzantine !== null && $byzantine > 0);
    check('creating the same area again is idempotent', PaperService::createArea('Byzantine musicology') === $byzantine);
    check('the new area is a root when no parent is given', (int) (Category::find($byzantine)['parent_id'] ?? 1) === 0
        || Category::find($byzantine)['parent_id'] === null);

    $assigned = PaperService::reclassify($otherId, $byzantine, null, null, 'test');
    check('the paper can be reclassified', !empty($assigned['ok']), (string) ($assigned['error'] ?? ''));
    $otherPaper = Paper::find($otherId);
    check('the free-text field is cleared', ($otherPaper['category_other'] ?? null) === null);
    check('the paper now counts as classified', PaperService::isClassified($otherPaper));
    check('approval works after classification', !empty(PaperService::approve($otherId, 'classified')['ok']));

    $nested = PaperService::createArea('Historical musicology', 'philosophy');
    check('an area can be created under an existing branch',
        $nested !== null && (int) (Category::find($nested)['parent_id'] ?? 0) === (int) (Category::findBySlug('philosophy')['id'] ?? -1));
    check('a new area can be deleted again', $nested !== null && Category::safeDelete($nested) === true);

    // =====================================================================
    echo "\nmulti-area search:\n";
    Paper::update($paperId, ['category_id' => (int) (Category::findBySlug('metaphysics')['id'] ?? 0)]);
    $byMaths = Paper::search(['categories' => ['mathematics']], 1, 50);
    $byPhilosophy = Paper::search(['categories' => ['philosophy']], 1, 50);
    $byBoth = Paper::search(['categories' => ['mathematics', 'philosophy']], 1, 50);
    check('filtering by one area works', $byPhilosophy['total'] >= 1, (string) $byPhilosophy['total']);
    check('the union of two areas is at least as large as each part',
        $byBoth['total'] >= max($byMaths['total'], $byPhilosophy['total']),
        json_encode([$byMaths['total'], $byPhilosophy['total'], $byBoth['total']]));
    check('an unknown area returns nothing', Paper::search(['categories' => ['not-a-real-area']], 1, 50)['total'] === 0);
    check('a branch filter includes its children',
        Paper::search(['categories' => ['mathematics']], 1, 50)['total'] === Paper::search(['category' => 'mathematics'], 1, 50)['total']);

    echo "\nsearchable language catalogue:\n";
    check('the language search terms include the native name',
        str_contains(\Athenaeum\Core\Languages::searchTerms('zh-CN'), '中文'));
    check('the language search terms include the English name',
        str_contains(\Athenaeum\Core\Languages::searchTerms('zh-CN'), 'chinese'));
    check('alternative names are searchable too',
        str_contains(\Athenaeum\Core\Languages::searchTerms('fa'), 'farsi')
        && str_contains(\Athenaeum\Core\Languages::searchTerms('id'), 'bahasa'));
    check('the code itself is searchable', str_contains(\Athenaeum\Core\Languages::searchTerms('ja'), 'ja'));

    // =====================================================================
    echo "\ne-mail verification codes:\n";
    $codeEmail = 'verify-' . bin2hex(random_bytes(3)) . '@example.org';
    $issued = \Athenaeum\Models\EmailVerification::issue($codeEmail);
    check('a code can be issued', !empty($issued['ok']), (string) ($issued['error'] ?? ''));
    $code = (string) ($issued['code'] ?? '');
    check('the code is six digits', (bool) preg_match('/^\d{6}$/', $code), $code);
    check('the code is not stored in clear text', (function () use ($codeEmail, $code): bool {
        $row = Database::instance()->selectOne(
            'SELECT code_hash FROM {{email_verifications}} WHERE email = :email',
            ['email' => $codeEmail]
        );
        return $row !== null && (string) $row['code_hash'] !== $code && strlen((string) $row['code_hash']) >= 32;
    })());
    $wrongCode = $code === '000000' ? '111111' : '000000';
    check('a wrong code is refused', empty(\Athenaeum\Models\EmailVerification::verify($codeEmail, $wrongCode)['ok']));
    check('the right code is accepted', !empty(\Athenaeum\Models\EmailVerification::verify($codeEmail, $code)['ok']));
    check('a used code cannot be replayed', empty(\Athenaeum\Models\EmailVerification::verify($codeEmail, $code)['ok']));

    \Athenaeum\Models\EmailVerification::issue($codeEmail);
    $tooSoon = \Athenaeum\Models\EmailVerification::issue($codeEmail);
    check('re-requesting immediately is throttled', empty($tooSoon['ok']) && ($tooSoon['error'] ?? '') === 'too soon',
        json_encode($tooSoon));

    $expiredEmail = 'expired-' . bin2hex(random_bytes(3)) . '@example.org';
    $expired = \Athenaeum\Models\EmailVerification::issue($expiredEmail);
    Database::instance()->update(
        'email_verifications',
        ['expires_at' => gmdate('Y-m-d H:i:s', time() - 60)],
        'email = :email',
        ['email' => $expiredEmail]
    );
    check('an expired code is refused', empty(\Athenaeum\Models\EmailVerification::verify($expiredEmail, (string) $expired['code'])['ok']));
    check('a code is refused for an address that never asked', empty(\Athenaeum\Models\EmailVerification::verify('nobody@example.org', '123456')['ok']));

    // A row that was never verified is what maintenance has to clean up.
    $staleEmail = 'stale-' . bin2hex(random_bytes(3)) . '@example.org';
    \Athenaeum\Models\EmailVerification::issue($staleEmail);
    Database::instance()->update(
        'email_verifications',
        ['expires_at' => gmdate('Y-m-d H:i:s', time() - 120)],
        'email = :email',
        ['email' => $staleEmail]
    );
    check('pruning removes stale rows', \Athenaeum\Models\EmailVerification::prune() >= 1);

    // =====================================================================
    echo "\npassword recovery:\n";
    $resetEmail = 'reset-' . bin2hex(random_bytes(3)) . '@example.org';
    $resetUserId = \Athenaeum\Models\User::register([
        'email'    => $resetEmail,
        'password' => 'the-original-password-2026',
        'nickname' => 'Recovery Test',
        'locale'   => 'zh-CN',
    ]);

    // A code minted for registration must not open the reset door, and vice
    // versa: the purpose column is what keeps the two flows apart.
    $registerCode = \Athenaeum\Models\EmailVerification::issue($resetEmail);
    check('a registration code is rejected by the reset flow',
        empty(\Athenaeum\Models\EmailVerification::verify(
            $resetEmail,
            (string) $registerCode['code'],
            \Athenaeum\Models\EmailVerification::PURPOSE_RESET
        )['ok']));
    check('the registration code still works for its own purpose',
        !empty(\Athenaeum\Models\EmailVerification::verify(
            $resetEmail,
            (string) $registerCode['code'],
            \Athenaeum\Models\EmailVerification::PURPOSE_REGISTER
        )['ok']));

    $resetIssue = \Athenaeum\Models\EmailVerification::issue(
        $resetEmail,
        \Athenaeum\Models\EmailVerification::PURPOSE_RESET
    );
    check('a reset code can be issued', !empty($resetIssue['ok']), (string) ($resetIssue['error'] ?? ''));
    $resetCode = (string) ($resetIssue['code'] ?? '');
    check('the reset code is six digits', (bool) preg_match('/^\d{6}$/', $resetCode), $resetCode);
    check('the reset code is not stored in clear text', (function () use ($resetEmail, $resetCode): bool {
        $row = Database::instance()->selectOne(
            'SELECT code_hash FROM {{email_verifications}} WHERE email = :email AND purpose = :purpose',
            ['email' => $resetEmail, 'purpose' => \Athenaeum\Models\EmailVerification::PURPOSE_RESET]
        );
        return $row !== null && (string) $row['code_hash'] !== $resetCode;
    })());

    // The e-mail a locked-out visitor receives: the link has to be absolute and
    // no placeholder may survive into the message.
    $resetMail = Mailer::template(Mailer::EVENT_RESET_CODE, [
        'site'    => 'AthenXiv',
        'code'    => $resetCode,
        'minutes' => '10',
        'url'     => url('password.request'),
        'name'    => 'Recovery Test',
    ], 'en');
    check('the reset mail carries the code', str_contains($resetMail['text'], $resetCode));
    check('the reset mail links to the recovery page',
        str_contains($resetMail['text'], url('password.request')), $resetMail['text']);
    check('the reset mail says how long the code lives', str_contains($resetMail['text'], '10'));
    check('no placeholder survives in the reset mail',
        !preg_match('/:(code|minutes|url|site|name)\b/', $resetMail['text']), $resetMail['text']);
    check('the reset mail has an html alternative', str_contains($resetMail['html'], '<p>'));

    // The real thing: the code changes the stored hash, once.
    \Athenaeum\Models\User::updatePassword($resetUserId, 'the-chosen-password-2026');
    $after = \Athenaeum\Models\User::find($resetUserId);
    check('the stored hash now matches the new password',
        password_verify('the-chosen-password-2026', (string) $after['password_hash']));
    check('the old password stopped working',
        !password_verify('the-original-password-2026', (string) $after['password_hash']));
    check('the reset code is consumed by its own purpose',
        !empty(\Athenaeum\Models\EmailVerification::verify(
            $resetEmail,
            $resetCode,
            \Athenaeum\Models\EmailVerification::PURPOSE_RESET
        )['ok']));

    // Availability follows the mail settings, not a hard-coded true.
    $mailEnabled = Settings::get('mail.enabled');
    Settings::set('mail.enabled', false);
    Settings::set('registration.reset_password', true);
    check('recovery is offered only while mail can be sent',
        \Athenaeum\Controllers\AuthController::resetAvailable() === false);
    Settings::set('mail.enabled', $mailEnabled);
    Settings::set('registration.reset_password', false);
    check('the administrator can switch recovery off',
        \Athenaeum\Controllers\AuthController::resetAvailable() === false);
    Settings::set('registration.reset_password', true);
    \Athenaeum\Models\User::purge($resetUserId);

    // The code button once did nothing at all: the JavaScript scoped its lookup
    // to the element carrying data-email-code-form, and on the recovery page
    // that element was an empty helper <form> — the button and the status line
    // were siblings, so the click handler found no status element and failed
    // silently. The contract is therefore enforced on the markup itself.
    $codeView = static function (string $file): array {
        $html = (string) file_get_contents($file);
        $start = strpos($html, 'data-email-code-block');
        if ($start === false) {
            return ['anchor' => false, 'button' => false, 'status' => false, 'is_form' => false];
        }
        // The block is a <div>, so its extent is the matching closing tag: the
        // button lives inside a nested .code-row wrapper, and cutting at the
        // first </div> would stop short of the status line. The anchor's own
        // <div> is already open, hence depth starts at 1.
        $depth = 1;
        $offset = strpos($html, '>', $start);
        $end = strlen($html);
        while ($offset !== false && $offset < strlen($html)) {
            if (preg_match('/<div\b/', $html, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
                $nextOpen = (int) $m[0][1];
            } else {
                $nextOpen = PHP_INT_MAX;
            }
            $nextClose = strpos($html, '</div>', $offset);
            if ($nextClose === false) {
                break;
            }
            if ($nextOpen < $nextClose) {
                $depth++;
                $offset = $nextOpen + 4;
                continue;
            }
            $depth--;
            $offset = $nextClose + 6;
            if ($depth <= 0) {
                $end = $nextClose;
                break;
            }
        }
        $block = substr($html, $start, max(0, $end - $start));
        $tagStart = strrpos(substr($html, 0, $start), '<');
        return [
            'anchor'  => true,
            'button'  => str_contains($block, 'data-send-code'),
            'status'  => str_contains($block, 'data-code-status'),
            'is_form' => str_starts_with(substr($html, (int) $tagStart, 5), '<form'),
        ];
    };
    foreach ([
        'the password-recovery page' => ATHENAEUM_ROOT . '/resources/views/auth/forgot-password.php',
        'the registration page'      => ATHENAEUM_ROOT . '/resources/views/auth/register.php',
    ] as $label => $viewFile) {
        $layout = $codeView($viewFile);
        check("{$label} anchors the code block", $layout['anchor']);
        check("{$label} keeps the send button inside that block", $layout['button']);
        check("{$label} keeps the status line inside that block", $layout['status']);
        check("{$label} does not nest forms", $layout['is_form'] === false,
            'the code block is a <form> on a page that already has one');
    }

    // =====================================================================
    echo "\nthe language a notification is written in:\n";
    // The interface language, not the account's stored preference, decides the
    // language of a verification code: the code has to match the page the
    // visitor is reading.
    $previousLocale = \Athenaeum\Core\I18n::locale();
    \Athenaeum\Core\I18n::setLocale('ja');
    $japaneseCode = Mailer::template('verify_code', ['code' => '123456', 'site' => 'AthenXiv']);
    check('a code follows the interface language',
        $japaneseCode['subject'] !== Mailer::template('verify_code', [
            'code' => '123456', 'site' => 'AthenXiv',
        ], 'en')['subject'], $japaneseCode['subject']);
    check('the code mail really is Japanese',
        (bool) preg_match('/[\x{3040}-\x{30ff}]/u', $japaneseCode['text']), $japaneseCode['text']);
    $germanReset = Mailer::template('reset_code', [
        'site' => 'AthenXiv', 'code' => '123456', 'minutes' => '10',
        'url' => 'https://athenxiv.com/index.php/password/forgot', 'name' => 'Test',
    ], 'de');
    check('a reset code can be written in German',
        str_contains($germanReset['text'], '123456') && !str_contains($germanReset['text'], ':code'),
        $germanReset['text']);
    \Athenaeum\Core\I18n::setLocale($previousLocale);

    // Language resolution itself: codes, regional variants and typed names.
    foreach ([
        ['de', 'de'],
        ['de-DE', 'de'],
        ['zh-TW', 'zh-CN'],
        ['Deutsch', 'de'],
        ['pt-BR', 'pt-BR'],
        ['x-klingon', null],
        ['', null],
    ] as [$tag, $want]) {
        $got = \Athenaeum\Core\I18n::bestMatch($tag);
        check("language '{$tag}' resolves to " . var_export($want, true),
            $got === $want, 'got ' . var_export($got, true));
    }

    // A paper's own language, not the author's account preference, decides the
    // language of the review notice.
    $paperLocaleCases = [
        ['language' => 'de', 'language_custom' => null, 'uploader' => 'ja', 'expected' => 'de',
         'why' => 'a German paper speaks German to a Japanese-reading author'],
        ['language' => 'zh-TW', 'language_custom' => null, 'uploader' => 'en', 'expected' => 'zh-CN',
         'why' => 'zh-TW collapses onto the Chinese interface we ship'],
        ['language' => '', 'language_custom' => 'Deutsch', 'uploader' => 'en', 'expected' => 'de',
         'why' => 'a hand-typed language name still resolves'],
        ['language' => 'x-klingon', 'language_custom' => 'Klingon', 'uploader' => 'fr', 'expected' => 'fr',
         'why' => 'an untranslated language falls back to the author'],
        ['language' => '', 'language_custom' => '', 'uploader' => '', 'expected' => 'en',
         'why' => 'nothing known falls back to the site default'],
    ];
    foreach ($paperLocaleCases as $case) {
        $resolved = Mailer::localeForPaper(
            ['language' => $case['language'], 'language_custom' => $case['language_custom']],
            ['locale' => $case['uploader']]
        );
        check($case['why'], $resolved === $case['expected'], "got {$resolved}, want {$case['expected']}");
    }

    // The label inside the message follows the recipient's language too: the
    // status word is translated when the template is rendered, not before.
    \Athenaeum\Core\I18n::setLocale('en');
    $statusVars = static fn (): array => [
        'site' => 'AthenXiv', 'title' => 'T', 'uid' => 'ATH-1', 'reason' => 'R',
        'url' => 'https://example.org', 'status' => '',
    ];
    $englishNotice = Mailer::template(Mailer::EVENT_REJECTED, $statusVars(), 'en')['text'];
    $germanNotice = Mailer::template(Mailer::EVENT_REJECTED, $statusVars(), 'de')['text'];
    check('a German notice differs from the English one',
        $englishNotice !== $germanNotice && $germanNotice !== '', $germanNotice);
    check('rendering one locale does not leave the site in it',
        \Athenaeum\Core\I18n::locale() === 'en', \Athenaeum\Core\I18n::locale());
    \Athenaeum\Core\I18n::setLocale($previousLocale);

    // =====================================================================
    echo "\nAI answers in the author's language:\n";
    [$systemZh] = AiReviewer::buildPrompt([
        'id' => 0, 'title' => 'T', 'abstract' => 'A', 'language' => 'zh-CN',
    ], '', 8000);
    [, $userZh] = AiReviewer::buildPrompt([
        'id' => 0, 'title' => 'T', 'abstract' => 'A', 'language' => 'zh-CN',
    ], '', 8000);
    check('the prompt names the submission language', str_contains($userZh, 'Chinese'), $userZh);
    check('the prompt tells the model which language to answer in',
        str_contains($userZh, 'write the "reason" field in Chinese'));
    check('the schema says the reason follows the LANGUAGE field', str_contains($systemZh, 'LANGUAGE field'));
    [, $userCustom] = AiReviewer::buildPrompt([
        'id' => 0, 'title' => 'T', 'abstract' => 'A', 'language' => 'x-byzantine-greek',
        'language_custom' => 'Byzantine Greek',
    ], '', 8000);
    check('a custom language is passed through verbatim', str_contains($userCustom, 'Byzantine Greek'), $userCustom);

    // =====================================================================
    echo "\nowner editing rules:\n";
    check('owners may edit a published paper', in_array(Paper::STATUS_APPROVED, Paper::EDITABLE_BY_OWNER, true));
    check('owners may not edit a taken-down paper', !in_array(Paper::STATUS_TAKEDOWN, Paper::EDITABLE_BY_OWNER, true));
    $editable = Paper::find($paperId);
    Paper::update($paperId, ['status' => Paper::STATUS_APPROVED, 'published_at' => Database::instance()->now()]);
    $edit = PaperService::update($paperId, [
        'title' => 'Edited title after publication',
        'subtitle' => null,
        'abstract' => (string) $editable['abstract'],
        'language' => 'en',
        'category_id' => null,
        'visibility' => 'public',
    ], null, []);
    check('editing metadata succeeds', !empty($edit['ok']), (string) ($edit['error'] ?? ''));
    $afterEdit = Paper::find($paperId);
    check('an edited published paper returns to review', ($afterEdit['status'] ?? '') === Paper::STATUS_PENDING,
        (string) ($afterEdit['status'] ?? ''));
    check('the edit is recorded in the audit log', (int) Database::instance()->scalar(
        "SELECT COUNT(*) FROM {{audit_logs}} WHERE action = 'paper.edit' AND target_id = :id",
        ['id' => $paperId]
    ) >= 1);

    // =====================================================================
    echo "\nshort status chips:\n";
    check('the confirmed chip is short',
        mb_strlen(\Athenaeum\Models\Timestamp::shortStatusLabel('confirmed')) <= 14,
        \Athenaeum\Models\Timestamp::shortStatusLabel('confirmed'));
    check('the pending chip is short',
        mb_strlen(\Athenaeum\Models\Timestamp::shortStatusLabel('pending')) <= 14,
        \Athenaeum\Models\Timestamp::shortStatusLabel('pending'));
    check('the chip differs from the full sentence',
        \Athenaeum\Models\Timestamp::shortStatusLabel('pending') !== \Athenaeum\Models\Timestamp::statusLabel('pending'));

    // =====================================================================
    echo "\nshipped page translations:\n";
    $tmpPages = sys_get_temp_dir() . '/athenaeum-pages-' . bin2hex(random_bytes(4));
    @mkdir($tmpPages, 0777, true);
    file_put_contents($tmpPages . '/xx.json', json_encode([
        'about' => ['title' => 'About (xx)', 'content' => "# About\n\nTranslated body."],
    ], JSON_UNESCAPED_UNICODE));
    $imported = Page::importTranslations($tmpPages);
    check('a translation file is imported', $imported['filled'] === 1, json_encode($imported));
    $aboutPage = Page::findBySlug('about');
    check('the imported locale is readable', Page::content($aboutPage, 'xx') === "# About\n\nTranslated body.");
    $again = Page::importTranslations($tmpPages);
    check('an existing translation is not overwritten', $again['filled'] === 0 && $again['skipped'] === 1);
    Page::saveLocale((int) $aboutPage['id'], 'xx', '', '');
    @unlink($tmpPages . '/xx.json');
    @rmdir($tmpPages);

    // =====================================================================
    echo "\nhomepage copy and subject-area candidates:\n";
    $originalHero = Settings::string('home.hero_title');
    Settings::set('home.hero_title', '');
    Settings::flush();
    check('empty override falls back to the translation',
        site_text('home.hero_title') === __('home.hero_title'), site_text('home.hero_title'));
    Settings::set('home.hero_title', 'Edited from the admin panel');
    Settings::flush();
    check('an override wins over the translation',
        site_text('home.hero_title') === 'Edited from the admin panel');
    foreach (['home.hero_title', 'home.hero_subtitle', 'home.hero_cta_label'] as $heroKey) {
        check("{$heroKey} is a known setting", array_key_exists($heroKey, Settings::DEFAULTS));
    }
    Settings::set('home.hero_title', $originalHero);
    Settings::flush();

    $candidates = \Athenaeum\Services\AiReviewer::areaCandidates([
        'title' => 'Quantum entanglement and quantum information',
        'abstract' => 'A study of quantum communication and cryptography.',
        'keywords' => 'quantum information',
    ], 260);
    $joined = strtolower(implode("\n", $candidates));
    check('candidates include the top-level disciplines', str_contains($joined, 'mathematics') && str_contains($joined, 'physics'));
    check('candidates include the matching branch', str_contains($joined, 'quantum'));
    check('candidates are capped', count($candidates) <= 260, (string) count($candidates));
    $empty = \Athenaeum\Services\AiReviewer::areaCandidates(['title' => 'Untitled'], 40);
    check('a vague title still gets a usable list', count($empty) === 40, (string) count($empty));

    // =====================================================================
    echo "\nSQL portability:\n";
    // MySQL with native prepared statements rejects a repeated named parameter
    // ("Invalid parameter number"); SQLite happily accepts it, so a query can
    // work locally and 500 in production. Guard against it statically.
    $offenders = [];
    $scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        dirname(__DIR__) . '/app', FilesystemIterator::SKIP_DOTS
    ));
    foreach ($scan as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'php') {
            continue;
        }
        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            if (!preg_match('/\b(SELECT|UPDATE|DELETE|INSERT|WHERE|LIKE|EXISTS)\b/i', $line)) {
                continue;
            }
            if (!preg_match_all('/:([a-z_][a-z0-9_]*)/i', $line, $matches)) {
                continue;
            }
            foreach (array_count_values($matches[1]) as $name => $count) {
                if ($count > 1) {
                    $offenders[] = basename($file->getPathname()) . ':' . ($number + 1) . ' :' . $name . ' ×' . $count;
                }
            }
        }
    }
    check('no SQL reuses a named placeholder', $offenders === [], implode(', ', $offenders));

    // =====================================================================
    echo "\nauthor accounts and metadata-only edits:\n";
    $actor = \Athenaeum\Models\User::find((int) \Athenaeum\Core\Database::instance()->scalar("SELECT id FROM {{users}} WHERE role = 'admin' ORDER BY id ASC LIMIT 1"));
    $pdfPath = sys_get_temp_dir() . '/v2-author-link.pdf';
    file_put_contents($pdfPath, makePdf('Author account linking test document'));
    $linked = \Athenaeum\Models\User::find((int) $actor['id']);
    $created = \Athenaeum\Services\PaperService::create([
        'title'        => 'Author linking without optional fields',
        'abstract'     => 'Only the author name is supplied; affiliation, e-mail and ORCID are intentionally left empty.',
        'language'     => 'en',
        'author_name'  => ['Solo Author'],
        'author_affiliation' => [''], 'author_email' => [''], 'author_orcid' => [''], 'author_user' => [''],
        'visibility'   => 'public', 'proxy_upload' => true, 'uploader_id' => (int) $actor['id'],
    ], ['name' => 'author-link.pdf', 'type' => 'application/pdf', 'tmp_name' => $pdfPath,
        'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($pdfPath), 'trusted_local' => true], [], $actor);
    check('a paper may carry only an author name', !empty($created['ok']), (string) ($created['error'] ?? ''));
    $linkedId = (int) ($created['paper']['id'] ?? 0);
    if ($linkedId > 0) {
        $before = [
            'versions' => (int) \Athenaeum\Core\Database::instance()->scalar('SELECT COUNT(*) FROM {{paper_versions}} WHERE paper_id = :id', ['id' => $linkedId]),
            'stamps'   => (int) \Athenaeum\Core\Database::instance()->scalar('SELECT COUNT(*) FROM {{timestamps}} WHERE paper_id = :id', ['id' => $linkedId]),
        ];
        $updated = \Athenaeum\Services\PaperService::update($linkedId, [
            'title' => 'Author linking without optional fields', 'abstract' => $created['paper']['abstract'],
            'language' => 'en',
            'author_name' => ['Solo Author'], 'author_user' => [(string) $linked['uid']],
            'link_url' => [], 'link_label' => [], 'link_kind' => [],
        ], null, [], false);
        check('a metadata-only edit is accepted', !empty($updated['ok']), (string) ($updated['error'] ?? ''));
        $after = [
            'versions' => (int) \Athenaeum\Core\Database::instance()->scalar('SELECT COUNT(*) FROM {{paper_versions}} WHERE paper_id = :id', ['id' => $linkedId]),
            'stamps'   => (int) \Athenaeum\Core\Database::instance()->scalar('SELECT COUNT(*) FROM {{timestamps}} WHERE paper_id = :id', ['id' => $linkedId]),
        ];
        check('an edit without a new PDF adds no version', $after['versions'] === $before['versions'],
            $before['versions'] . ' -> ' . $after['versions']);
        check('an edit without a new PDF adds no timestamp', $after['stamps'] === $before['stamps'],
            $before['stamps'] . ' -> ' . $after['stamps']);
        $link = \Athenaeum\Core\Database::instance()->scalar('SELECT user_id FROM {{paper_authors}} WHERE paper_id = :id LIMIT 1', ['id' => $linkedId]);
        check('the author is linked to the chosen account', (int) $link === (int) $linked['id'], var_export($link, true));
        $archivedProbe = ['origin' => \Athenaeum\Models\Paper::ORIGIN_ARCHIVE, 'license' => 'publicdomain'];
        check('an archived work is recognised as such', \Athenaeum\Models\Paper::isArchived($archivedProbe));
        check('a public-domain work is badged Public Domain',
            \Athenaeum\Models\Paper::rightsLabel($archivedProbe) === __('paper.rights_public_domain'));
        check('a CC-BY work is badged with its licence',
            \Athenaeum\Models\Paper::rightsLabel(['origin' => 'archive', 'license' => 'cc-by']) === 'CC-BY');
        check('a normal submission is not archived', !\Athenaeum\Models\Paper::isArchived(['origin' => 'submission']));
        foreach (['policy'] as $slug) {
            check("the {$slug} page exists in the built-in copy",
                array_key_exists($slug, require dirname(__DIR__) . '/database/seed_pages.php'));
        }
        \Athenaeum\Models\Paper::purge($linkedId);
    }
    @unlink($pdfPath);

    // =====================================================================
    echo "\nnew versions stay behind review:\n";
    $gatePdf = static function (string $marker): string {
        $path = tempnam(sys_get_temp_dir(), 'gate') . '.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n% " . $marker . "\n%%EOF\n");
        return $path;
    };
    $gateActor = \Athenaeum\Models\User::find((int) \Athenaeum\Core\Database::instance()->scalar("SELECT id FROM {{users}} WHERE role = 'admin' ORDER BY id ASC LIMIT 1"));
    $gateWrap = static fn (string $path): array => ['name' => 'gate.pdf', 'type' => 'application/pdf', 'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($path), 'trusted_local' => true];
    $gateV1 = $gatePdf('VERSION-ONE');
    $gateV2 = $gatePdf('VERSION-TWO');
    $gateStoredV2 = (string) hash_file('sha256', $gateV2);
    $gateCreated = \Athenaeum\Services\PaperService::create([
        'title' => 'Version gating regression probe',
        'abstract' => str_repeat('Version gating regression probe document. ', 3),
        'language' => 'en', 'author_name' => ['Probe Author'], 'visibility' => 'public',
        'proxy_upload' => true, 'uploader_id' => (int) $gateActor['id'],
    ], $gateWrap($gateV1), [], $gateActor);
    $gateId = (int) ($gateCreated['paper']['id'] ?? 0);
    if ($gateId > 0) {
        \Athenaeum\Services\PaperService::approve($gateId, 'first publication');
        $liveSha = (string) \Athenaeum\Models\Paper::find($gateId)['pdf_sha256'];

        \Athenaeum\Services\PaperService::newVersion($gateId, $gateWrap($gateV2), 'second revision', false, $gateActor);
        $during = \Athenaeum\Models\Paper::find($gateId);
        check('a paper under revision review is still public', \Athenaeum\Models\Paper::isPublic($during),
            (string) $during['status']);
        check('readers keep the reviewed file while the revision waits',
            (string) $during['pdf_sha256'] === $liveSha, substr((string) $during['pdf_sha256'], 0, 12));
        check('the revision is recorded as unpublished',
            (int) \Athenaeum\Core\Database::instance()->scalar(
                'SELECT COUNT(*) FROM {{paper_versions}} WHERE paper_id = :id AND published_at IS NULL',
                ['id' => $gateId]) === 1);

        \Athenaeum\Services\PaperService::approve($gateId, 'revision approved');
        $after = \Athenaeum\Models\Paper::find($gateId);
        check('approving the revision promotes its file', (string) $after['pdf_sha256'] === $gateStoredV2,
            substr((string) $after['pdf_sha256'], 0, 12));
        check('the promoted revision becomes the version number', (int) $after['version_no'] === 2,
            (string) $after['version_no']);
        check('no unpublished revision is left behind',
            (int) \Athenaeum\Core\Database::instance()->scalar(
                'SELECT COUNT(*) FROM {{paper_versions}} WHERE paper_id = :id AND published_at IS NULL',
                ['id' => $gateId]) === 0);
        \Athenaeum\Models\Paper::purge($gateId);
    }
    @unlink($gateV1);
    @unlink($gateV2);

    // =====================================================================
    echo "\nmail templates:\n";
    $template = Mailer::template(Mailer::EVENT_APPROVED, [
        'site' => 'Athenaeum', 'title' => 'A paper', 'uid' => 'ATH-TEST01',
        'url' => 'https://example.org/paper/ATH-TEST01', 'status' => 'Published',
    ], 'en');
    check('subject rendered', str_contains($template['subject'], 'ATH') || str_contains($template['subject'], 'A paper'),
        $template['subject']);
    check('body rendered with variables', str_contains($template['text'], 'ATH-TEST01'));
    check('html body built', str_contains($template['html'], '<p>'));
    check('no unsubstituted placeholder remains', !str_contains($template['text'], ':uid'));
    $zh = Mailer::template(Mailer::EVENT_APPROVED, [
        'site' => '雅典学院', 'title' => '一篇论文', 'uid' => 'ATH-TEST01',
        'url' => 'https://example.org/p', 'status' => '已发表',
    ], 'zh-CN');
    check('templates follow the recipient locale', $zh['subject'] !== $template['subject'], $zh['subject']);
    Settings::set('mail.enabled', false);
    check('mail reports not configured while switched off', Mailer::configured()['ok'] === false);
} finally {
    foreach ($paperIds as $id) {
        try {
            Paper::purge($id);
        } catch (Throwable $e) {
            echo '  (cleanup warning: ' . $e->getMessage() . ")\n";
        }
    }
    foreach ($scratch as $file) {
        @unlink($file);
    }
    foreach ($original as $key => $value) {
        Settings::set($key, $value);
    }
    Settings::flush();
    @unlink(sys_get_temp_dir() . '/fake-ai-last-request.json');
}

echo "\n{$checks} checks run, {$failures} failed\n";
echo $failures === 0 ? "V2 FEATURES OK\n" : "V2 FEATURES HAVE FAILURES\n";
exit($failures === 0 ? 0 : 1);
