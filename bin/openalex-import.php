<?php
/**
 * Import openly licensed, highly cited papers from OpenAlex.
 *
 * Why it works in batches: the production host has no shell and the database
 * accepts no remote connections, so the importer runs inside PHP on the server
 * and is driven one batch at a time from outside. All progress lives in a state
 * file, so a batch that dies (timeout, 500) simply resumes where it stopped.
 *
 *   CLI   php bin/openalex-import.php --api-key=… --batch=4
 *   Web   include this file from a token-guarded runner; it returns instead of
 *         echoing, and the runner prints the summary as JSON.
 *
 * Phases
 *   plan     query OpenAlex once, bucket the most cited open-access works by
 *            discipline, keep N per discipline (breadth, not just medicine)
 *   fetch    download the PDF, verify it really is a PDF, respect the byte
 *            budget and the site's own per-file limit
 *   publish  create the paper through PaperService (so hashes, OpenTimestamps
 *            proofs, audit entries and taxonomy behave exactly as for a normal
 *            submission) and approve it with the source attributed
 *
 * Only licences that permit redistribution are imported: public domain, CC0,
 * CC BY and CC BY-SA. Each paper records its licence and links to the original.
 */

declare(strict_types=1);

// Never run from the web by accident: on hosts whose document root is the
// project root, /bin/openalex-import.php would otherwise start importing as
// soon as somebody requests it.
if (PHP_SAPI !== 'cli' && !defined('OPENALEX_RUNNER')) {
    http_response_code(404);
    exit;
}

// Works both from bin/ (CLI) and from the document root (the one-time web
// runner on a host whose web root is the project root).
$root = is_file(__DIR__ . '/app/bootstrap.php') ? __DIR__ : dirname(__DIR__);

/** @var array $config */
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Http;
use Athenaeum\Core\Languages;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;
use Athenaeum\Models\Category;
use Athenaeum\Models\Paper;
use Athenaeum\Models\Section;
use Athenaeum\Services\PaperService;

Config::load($config);

/** Web runners define this so the file reports instead of exiting. */
$webMode = defined('OPENALEX_RUNNER');

// ---------------------------------------------------------------------------
// Options (CLI flags or query string)
// ---------------------------------------------------------------------------
$options = [];
if ($webMode) {
    foreach ($_GET as $key => $value) {
        $options[str_replace('-', '_', (string) $key)] = is_scalar($value) ? (string) $value : '';
    }
} else {
    foreach (array_slice($argv ?? [], 1) as $argument) {
        if (preg_match('/^--([a-z0-9\-_]+)(?:=(.*))?$/i', (string) $argument, $matches)) {
            $options[str_replace('-', '_', $matches[1])] = $matches[2] ?? '1';
        }
    }
}

$option = static function (string $key, string $default = '') use ($options): string {
    return isset($options[$key]) && $options[$key] !== '' ? (string) $options[$key] : $default;
};

$stateFile = rtrim(Config::path('storage'), '/\\') . '/openalex-import.json';
// OpenAlex asks heavy users to identify themselves. The address belongs in
// `site.contact_email` (admin → settings), not in the source: this file is part
// of the public distribution.
$mailto = $option('mailto', Settings::string('site.contact_email', 'admin@example.org'));
$perField = max(1, (int) $option('per_field', '12'));
$planCap = max(1, (int) $option('plan_cap', '560'));
$scanPages = max(1, (int) $option('scan_pages', '20'));
$batch = max(1, (int) $option('batch', '4'));
$timeBox = max(10.0, (float) $option('seconds', '90'));
$maxPdfBytes = max(1, (int) $option('max_pdf_mb', (string) Settings::int('upload.max_pdf_mb', 10))) * 1024 * 1024;
$budgetBytes = (int) round(((float) $option('budget_mb', '340')) * 1024 * 1024);
$dryRun = $option('dry_run', '0') === '1';
$reset = $option('reset', '0') === '1';

/**
 * Last-resort mapping from OpenAlex's field names to our taxonomy slugs, used
 * when no area name matches. Without it a paper would be published with no
 * subject area at all.
 */
const FIELD_TO_SLUG = [
    'physics and astronomy' => 'physics',
    'chemistry' => 'chemistry',
    'mathematics' => 'mathematics',
    'computer science' => 'computer-science',
    'engineering' => 'engineering',
    'materials science' => 'materials-science',
    'medicine' => 'medicine',
    'health professions' => 'medicine',
    'nursing' => 'nursing',
    'neuroscience' => 'neuroscience',
    'biochemistry, genetics and molecular biology' => 'biochemistry',
    'biology' => 'biology',
    'immunology and microbiology' => 'microbiology',
    'pharmacology, toxicology and pharmaceutics' => 'pharmacology',
    'agricultural and biological sciences' => 'agriculture',
    'environmental science' => 'environmental-science',
    'earth and planetary sciences' => 'earth-sciences',
    'energy' => 'energy',
    'chemical engineering' => 'chemical-engineering',
    'economics, econometrics and finance' => 'economics',
    'business, management and accounting' => 'business',
    'social sciences' => 'social-sciences',
    'psychology' => 'psychology',
    'sociology' => 'sociology',
    'political science and international relations' => 'political-science',
    'arts and humanities' => 'history',
    'philosophy' => 'philosophy',
    'history' => 'history',
    'linguistics' => 'linguistics',
    'law' => 'law',
    'education' => 'education',
    'decision sciences' => 'decision-sciences',
    'multidisciplinary' => 'interdisciplinary',
    'other' => 'interdisciplinary',
];

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
$state = ['plan' => [], 'cursor' => 0, 'bytes' => 0, 'imported' => [], 'failed' => [], 'skipped' => [],
    'api_key' => '', 'planned_at' => null, 'log' => []];

if (is_file($stateFile)) {
    $loaded = json_decode((string) file_get_contents($stateFile), true);
    if (is_array($loaded)) {
        $state = array_merge($state, $loaded);
    }
}

// "reset" re-plans (to widen the selection once the first pass is exhausted)
// without losing the byte count, the already-imported list or the API key.
if ($reset) {
    $state['plan'] = [];
    $state['cursor'] = 0;
    $state['log'][] = 'plan reset (counters kept)';
}

$apiKey = $option('api_key', (string) $state['api_key']);
if ($apiKey !== '') {
    $state['api_key'] = $apiKey;
}

$save = static function () use (&$state, $stateFile): void {
    $state['log'] = array_slice($state['log'], -40);
    @file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};

$finish = static function (array $payload) use ($webMode, $save): array {
    $save();
    if ($webMode) {
        return $payload;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    return $payload;
};

// Diagnostics without touching the plan: what happened so far, and why the
// failures failed.
if ($option('report', '0') === '1') {
    $rows = Database::instance()->select('SELECT status, COUNT(*) AS n FROM {{papers}} GROUP BY status');
    $dupes = Database::instance()->select(
        'SELECT title, COUNT(*) AS n FROM {{papers}} GROUP BY title HAVING COUNT(*) > 1 ORDER BY n DESC LIMIT 10'
    );
    $withDoi = (int) Database::instance()->scalar("SELECT COUNT(*) FROM {{papers}} WHERE doi IS NOT NULL AND doi <> ''");
    return $finish([
        'ok' => true,
        'phase' => 'report',
        'plan' => count($state['plan']),
        'cursor' => $state['cursor'],
        'bytes' => $state['bytes'],
        'imported' => count($state['imported']),
        'skipped' => $state['skipped'],
        'failed' => count($state['failed']),
        'papers_by_status' => $rows,
        'papers_with_doi' => $withDoi,
        'duplicate_titles' => $dupes,
        'fields' => array_count_values(array_map(static fn ($i) => (string) ($i['field'] ?? '?'), $state['imported'])),
        'last_failures' => array_slice($state['failed'], -10),
        'last_imports' => array_slice($state['imported'], -5),
        'log' => array_slice($state['log'], -8),
    ]);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
$openalex = static function (string $path, array $params) use ($apiKey, $mailto): ?array {
    $params['api_key'] = $apiKey;
    $params['mailto'] = $mailto;
    $url = 'https://api.openalex.org/' . ltrim($path, '/') . '?' . http_build_query($params);
    $result = Http::get($url, ['Accept: application/json'], ['timeout' => 90]);
    if (!$result['ok']) {
        return null;
    }
    $decoded = json_decode($result['body'], true);
    return is_array($decoded) ? $decoded : null;
};

/** OpenAlex returns abstracts as an inverted index; rebuild the sentences. */
$abstractFromInverted = static function (?array $index): string {
    if (!is_array($index) || $index === []) {
        return '';
    }
    $positioned = [];
    foreach ($index as $word => $positions) {
        foreach ((array) $positions as $position) {
            $positioned[(int) $position] = (string) $word;
        }
    }
    ksort($positioned);
    return trim(preg_replace('/\s+/', ' ', implode(' ', $positioned)) ?? '');
};

/**
 * Preferred PDF hosts, best first. Publisher platforms answer 403 to a bot far
 * more often than repositories do, so a repository copy is tried first even
 * when OpenAlex calls the publisher copy the "best" location.
 */
$preferPdfHost = static function (string $url): int {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '' || str_contains($host, 'api.openalex.org')) {
        return 999;
    }
    $preferred = ['pmc.ncbi.nlm.nih.gov', 'europepmc.org', 'ncbi.nlm.nih.gov', 'arxiv.org',
        'biorxiv.org', 'medrxiv.org', 'zenodo.org', 'hal.science', 'doaj.org', 'core.ac.uk',
        'repec.org', 'ssrn.com', 'scielo', 'jstage.jst.go.jp', 'frontiersin.org', 'mdpi.com',
        'plos.org', 'springeropen.com', 'biomedcentral.com', '.edu', '.ac.', 'univ-', 'repository'];
    foreach ($preferred as $index => $needle) {
        if (str_contains($host, $needle)) {
            return $index;
        }
    }
    return 100;
};

/** Best-fitting subject area for a set of discipline names. */
$matchArea = static function (array $names) : ?array {
    $words = [];
    foreach ($names as $name) {
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $name)) ?: [] as $word) {
            // A purely numeric token (a year in a title, say) becomes an int
            // array key, so cast back before measuring it.
            $word = (string) $word;
            if (mb_strlen($word) >= 4 && !in_array($word, ['and', 'the', 'for', 'with', 'from', 'using', 'based', 'study', 'research'], true)) {
                $words[$word] = true;
            }
        }
    }
    if ($words === []) {
        return null;
    }
    $best = null;
    $bestScore = 0;
    foreach (Category::flat(false) as $category) {
        $label = mb_strtolower(Category::name($category, 'en') . ' ' . (string) $category['slug']);
        $score = 0;
        foreach (array_keys($words) as $key) {
            $word = (string) $key;
            $stem = mb_strlen($word) > 6 ? mb_substr($word, 0, mb_strlen($word) - 2) : $word;
            if ($stem !== '' && str_contains($label, $stem)) {
                $score++;
            }
        }
        if ($score === 0) {
            continue;
        }
        // Prefer the most specific match, then the shallower node (a broad area
        // is a safer home than an unrelated leaf that happens to share a word).
        $depth = (int) ($category['depth'] ?? 0);
        $rank = [$score, -$depth];
        if ($best === null || $rank > $best['rank']) {
            $best = ['id' => (int) $category['id'], 'slug' => (string) $category['slug'], 'rank' => $rank,
                'name' => Category::name($category, 'en')];
            $bestScore = $score;
        }
    }
    return $bestScore > 0 ? $best : null;
};

$downloadPdf = static function (array $urls, int $maxBytes, ?string &$error): ?string {    $ca = Http::caBundle();
    foreach ($urls as $url) {
        $url = (string) $url;
        if (!preg_match('#^https?://#i', $url)) {
            continue;
        }
        $temp = tempnam(sys_get_temp_dir(), 'oa-pdf-');
        if ($temp === false) {
            $error = 'cannot create temp file';
            return null;
        }
        $handle = fopen($temp, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_USERAGENT => 'AthenXivBot/1.0 (+https://athenxiv.com; open-access archiving)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($resource, float $total, float $now) use ($maxBytes): int {
                return $now > $maxBytes ? 1 : 0; // abort oversize downloads
            },
        ]);
        if ($ca !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = (string) curl_error($ch);
        curl_close($ch);
        fclose($handle);

        $size = is_file($temp) ? (int) filesize($temp) : 0;
        $magic = $size > 4 ? (string) file_get_contents($temp, false, null, 0, 5) : '';
        if ($status >= 200 && $status < 300 && $size > 2048 && str_starts_with($magic, '%PDF')) {
            return $temp;
        }
        $error = trim(sprintf('HTTP %d %s %dB %s', $status, $type, $size, $curlError));
        @unlink($temp);
    }
    return null;
};

$admin = Database::instance()->select(
    "SELECT * FROM {{users}} WHERE role = 'admin' ORDER BY id ASC LIMIT 1"
)[0] ?? null;

$sections = [];
foreach (Section::ordered(false) as $section) {
    $sections[(string) $section['slug']] = (int) $section['id'];
}

// ---------------------------------------------------------------------------
// Phase 1 — plan
// ---------------------------------------------------------------------------
if ($state['plan'] === []) {
    if ($apiKey === '') {
        return $finish(['ok' => false, 'error' => 'no OpenAlex api key; pass --api-key=…']);
    }
    $licences = trim((string) $option('licences', 'cc-by|cc-by-sa|cc0|publicdomain'));
    if ($licences === '') {
        $licences = 'cc-by|cc-by-sa|cc0|publicdomain';
    }
    $select = 'id,doi,title,publication_year,publication_date,cited_by_count,language,best_oa_location,locations,'
        . 'primary_topic,authorships,abstract_inverted_index,type,is_retracted,open_access';
    // "green" means the open copy sits in a repository (PubMed Central, arXiv,
    // a university server). Publisher-hosted copies answer 403 to a bot far more
    // often, so a second pass can insist on repository copies.
    $oaStatus = trim((string) $option('oa_status', ''));
    $filter = 'is_oa:true,type:article,has_fulltext:true,is_retracted:false,best_oa_location.license:' . $licences;
    if ($oaStatus !== '') {
        $filter .= ',open_access.oa_status:' . $oaStatus;
    }
    $buckets = [];
    $scanned = 0;
    for ($page = 1; $page <= $scanPages; $page++) {
        $data = $openalex('works', [
            'filter' => $filter,
            'sort' => 'cited_by_count:desc',
            'per-page' => 200,
            'page' => $page,
            'select' => $select,
        ]);
        if ($data === null || empty($data['results'])) {
            break;
        }
        foreach ($data['results'] as $work) {
            $scanned++;
            $field = (string) ($work['primary_topic']['field']['display_name'] ?? 'Other');
            if (count($buckets[$field] ?? []) >= $perField) {
                continue;
            }
            $authors = [];
            foreach (array_slice((array) ($work['authorships'] ?? []), 0, 25) as $authorship) {
                $name = trim((string) ($authorship['author']['display_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $affiliation = '';
                foreach ((array) ($authorship['institutions'] ?? []) as $institution) {
                    if (!empty($institution['display_name'])) {
                        $affiliation = (string) $institution['display_name'];
                        break;
                    }
                }
                $authors[] = ['name' => $name, 'affiliation' => $affiliation];
            }
            $buckets[$field][] = [
                'id' => (string) ($work['id'] ?? ''),
                'doi' => (string) ($work['doi'] ?? ''),
                'title' => trim((string) ($work['title'] ?? $work['display_name'] ?? '')),
                'year' => (int) ($work['publication_year'] ?? 0),
                'published_date' => trim((string) ($work['publication_date'] ?? '')),
                'cited' => (int) ($work['cited_by_count'] ?? 0),
                'lang' => (string) ($work['language'] ?? 'en'),
                'license' => (string) ($work['best_oa_location']['license'] ?? ''),
                'source' => (string) ($work['best_oa_location']['source']['display_name'] ?? ''),
                'landing' => (string) ($work['best_oa_location']['landing_page_url'] ?? $work['doi'] ?? ''),
                'topic' => array_values(array_filter([
                    (string) ($work['primary_topic']['display_name'] ?? ''),
                    (string) ($work['primary_topic']['subfield']['display_name'] ?? ''),
                    (string) ($work['primary_topic']['field']['display_name'] ?? ''),
                    (string) ($work['primary_topic']['domain']['display_name'] ?? ''),
                ])),
                // Kept separately: the subfield/field names are what the area
                // matcher should trust first.
                'topic_name' => (string) ($work['primary_topic']['display_name'] ?? ''),
                'subfield' => (string) ($work['primary_topic']['subfield']['display_name'] ?? ''),
                'authors' => $authors,
                'abstract' => mb_substr($abstractFromInverted($work['abstract_inverted_index'] ?? null), 0, 6000),
                'pdfs' => (static function (array $urls) use ($preferPdfHost): array {
                    $urls = array_values(array_unique(array_filter($urls, static fn ($u): bool => is_string($u) && $u !== '')));
                    usort($urls, static fn ($a, $b): int => $preferPdfHost($a) <=> $preferPdfHost($b));
                    return $urls;
                })(array_merge(
                    array_filter([(string) ($work['best_oa_location']['pdf_url'] ?? '')]),
                    array_filter([(string) ($work['open_access']['oa_url'] ?? '')]),
                    array_map(static fn (array $l): string => (string) ($l['pdf_url'] ?? ''), (array) ($work['locations'] ?? []))
                )),
            ];
        }
        if ($scanned >= $planCap * 3) {
            break;
        }
    }
    $plan = [];
    foreach ($buckets as $field => $works) {
        foreach ($works as $work) {
            $plan[] = $work + ['field' => $field];
        }
    }
    // Interleave disciplines so an interrupted run still has breadth.
    usort($plan, static fn (array $a, array $b): int => $b['cited'] <=> $a['cited']);
    $interleaved = [];
    $byField = [];
    foreach ($plan as $work) {
        $byField[$work['field']][] = $work;
    }
    $index = 0;
    while (count($interleaved) < count($plan) && count($interleaved) < $planCap) {
        $added = false;
        foreach (array_keys($byField) as $field) {
            if (isset($byField[$field][$index])) {
                $interleaved[] = $byField[$field][$index];
                $added = true;
                if (count($interleaved) >= $planCap) {
                    break;
                }
            }
        }
        if (!$added) {
            break;
        }
        $index++;
    }
    $state['plan'] = $interleaved;
    $state['planned_at'] = Database::instance()->now();
    $state['log'][] = sprintf('planned %d works from %d disciplines (scanned %d)', count($interleaved), count($byField), $scanned);
    $save();

    if ($option('plan_only', '0') === '1' || $dryRun) {
        return $finish(['ok' => true, 'phase' => 'plan', 'planned' => count($interleaved),
            'disciplines' => array_map(static fn ($f, $w) => [$f, count($w)], array_keys($byField), array_values($byField)),
            'scanned' => $scanned]);
    }
}

// ---------------------------------------------------------------------------
// Phase 2 — fetch + publish
// ---------------------------------------------------------------------------
$summary = ['ok' => true, 'phase' => 'import', 'plan' => count($state['plan']), 'cursor' => $state['cursor'],
    'imported' => count($state['imported']), 'failed' => count($state['failed']), 'skipped' => count($state['skipped']),
    'bytes' => $state['bytes'], 'bytes_budget' => $budgetBytes, 'batch' => [], 'done' => false];

if ($admin === null) {
    return $finish(['ok' => false, 'error' => 'no administrator account to attribute the import to']);
}

$processed = 0;
$startedAt = microtime(true);
while ($processed < $batch && $state['cursor'] < count($state['plan'])) {
    if (microtime(true) - $startedAt > $timeBox) {
        $summary['time_box'] = true;
        break;
    }
    $work = $state['plan'][$state['cursor']];
    $state['cursor']++;
    $processed++;

    if ($state['bytes'] >= $budgetBytes) {
        $state['log'][] = 'byte budget reached';
        break;
    }

    // Skip anything already on the site (same DOI) so reruns are safe.
    $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', (string) $work['doi']);
    if ($doi !== '') {
        $exists = Database::instance()->scalar('SELECT id FROM {{papers}} WHERE doi = :doi LIMIT 1', ['doi' => $doi]);
        if ($exists) {
            $state['skipped'][] = ['doi' => $doi, 'why' => 'already present'];
            continue;
        }
    }

    $error = null;
    $temp = $downloadPdf((array) $work['pdfs'], $maxPdfBytes, $error);
    if ($temp === null) {
        $state['failed'][] = ['title' => $work['title'], 'why' => 'download: ' . (string) $error];
        $summary['batch'][] = ['title' => mb_substr((string) $work['title'], 0, 60), 'status' => 'download-failed'];
        continue;
    }
    $size = (int) filesize($temp);

    // OpenAlex states the discipline at several levels. The subfield and field
    // names are authoritative and short ("Infectious Diseases", "Medicine"),
    // while the topic title often reads like a sentence and drags the match
    // towards whatever area happens to share a common word. Try them in order of
    // authority, and only fall back to the paper's own words last.
    $area = $matchArea(array_filter([(string) ($work['subfield'] ?? ''), (string) ($work['field'] ?? '')]))
        ?? $matchArea(array_filter([(string) ($work['topic_name'] ?? '')]))
        ?? $matchArea(array_merge((array) $work['topic'], [$work['title']]));
    if ($area === null) {
        $slug = FIELD_TO_SLUG[mb_strtolower(trim((string) ($work['field'] ?? '')))] ?? null;
        $fallback = $slug !== null ? Category::findBySlug($slug) : null;
        if ($fallback !== null) {
            $area = ['id' => (int) $fallback['id'], 'slug' => (string) $fallback['slug'],
                'name' => Category::name($fallback, 'en'), 'rank' => [0, 0]];
        }
    }
    $sectionId = $sections['preprints'] ?? null;
    if ($work['cited'] >= 1000 && isset($sections['tier-1'])) {
        $sectionId = $sections['tier-1'];
    } elseif ($work['cited'] >= 100 && isset($sections['tier-2'])) {
        $sectionId = $sections['tier-2'];
    }

    $language = (string) $work['lang'];
    if ($language === '' || !Languages::exists($language)) {
        $language = 'en';
    }

    // Dates shown on the paper page: when the work was first published, and —
    // only when it can be stated honestly — when its copyright term ended.
    $publishedAt = null;
    $rawDate = trim((string) ($work['published_date'] ?? ''));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $rawDate, $dateMatch) === 1) {
        $publishedAt = $dateMatch[1] . '-' . $dateMatch[2] . '-' . $dateMatch[3];
    } elseif ((int) $work['year'] > 0) {
        $publishedAt = sprintf('%04d-01-01', (int) $work['year']);
    }

    $licence = str_replace([' ', '_'], '-', strtolower((string) $work['license']));
    $copyrightExpiredAt = null;
    if (in_array($licence, Paper::PUBLIC_DOMAIN_LICENCES, true) && $publishedAt !== null) {
        // US works published before 1978 fall into the public domain 95 years
        // after publication. Only record the date once that term has actually
        // passed; otherwise the rights status is shown without a date rather
        // than inventing one.
        $candidate = sprintf('%04d-01-01', ((int) substr($publishedAt, 0, 4)) + 95);
        if ($candidate <= date('Y-m-d')) {
            $copyrightExpiredAt = $candidate;
        }
    }

    $abstract = trim((string) $work['abstract']);
    if (mb_strlen($abstract) < 40) {
        // The validator wants a real abstract; describe the record honestly.
        $abstract = sprintf(
            'Imported from OpenAlex (%s). Cited %s times. %s',
            $work['source'] !== '' ? $work['source'] : 'open access',
            number_format((int) $work['cited']),
            $work['year'] > 0 ? 'Published ' . $work['year'] . '.' : ''
        );
    }

    $input = [
        'title' => mb_substr((string) $work['title'], 0, 300),
        'subtitle' => null,
        'abstract' => mb_substr($abstract, 0, 20000),
        'language' => $language,
        'language_custom' => null,
        'section_id' => $sectionId,
        'category_id' => $area['id'] ?? null,
        'category_other' => null,
        'keywords' => mb_substr(implode(', ', array_slice((array) $work['topic'], 0, 4)), 0, 500),
        'license' => $work['license'] !== '' ? strtoupper((string) $work['license']) : null,
        'doi' => $doi !== '' ? $doi : null,
        'visibility' => 'public',
        'author_name' => array_map(static fn (array $a): string => $a['name'], (array) $work['authors']),
        'author_affiliation' => array_map(static fn (array $a): string => $a['affiliation'], (array) $work['authors']),
        'author_email' => [],
        'author_orcid' => [],
        'author_corresponding' => [],
        'link_label' => array_values(array_filter([
            $work['source'] !== '' ? mb_substr((string) $work['source'], 0, 80) : 'Source',
        ])),
        'link_url' => array_values(array_filter([(string) $work['landing']])),
        'link_kind' => $doi !== '' ? ['doi'] : ['other'],
        'as_draft' => false,
        'size_exempt' => false,
        'size_exempt_note' => null,
        'proxy_upload' => true,
        'uploader_id' => (int) $admin['id'],
        'version_note' => null,
        // Mark it as an archived work and record the dates the UI shows.
        'origin' => Paper::ORIGIN_ARCHIVE,
        'origin_source' => $work['source'] !== '' ? (string) $work['source'] : 'OpenAlex',
        'origin_published_at' => $publishedAt,
        'copyright_expired_at' => $copyrightExpiredAt,
    ];

    $file = [
        'name' => Str::slug((string) $work['title'], 80) . '.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $temp,
        'error' => UPLOAD_ERR_OK,
        'size' => $size,
        // We fetched this file ourselves: it is not an HTTP upload, so the
        // uploader's is_uploaded_file() check must be waived for it.
        'trusted_local' => true,
    ];

    $result = $dryRun
        ? ['ok' => true, 'paper' => ['id' => 0, 'uid' => 'DRY-RUN']]
        : PaperService::create($input, $file, [], $admin);

    if (empty($result['ok'])) {
        @unlink($temp);
        $state['failed'][] = ['title' => $work['title'], 'why' => 'create: ' . (string) ($result['error'] ?? '?')];
        $summary['batch'][] = ['title' => mb_substr((string) $work['title'], 0, 60), 'status' => 'create-failed',
            'error' => (string) ($result['error'] ?? '')];
        continue;
    }

    $paper = $result['paper'] ?? [];
    $paperId = (int) ($paper['id'] ?? 0);
    if (!$dryRun && $paperId > 0) {
        PaperService::approve($paperId, sprintf(
            'Imported from OpenAlex (%s, %s) under licence %s; original: %s',
            $work['source'] !== '' ? $work['source'] : 'open access source',
            $work['year'] > 0 ? (string) $work['year'] : 'n.d.',
            $work['license'] !== '' ? (string) $work['license'] : 'open access',
            (string) $work['landing']
        ), $sectionId !== null ? (string) $sectionId : null);
    }

    $state['bytes'] += $size;
    $state['imported'][] = ['uid' => (string) ($paper['uid'] ?? ''), 'title' => $work['title'],
        'bytes' => $size, 'area' => $area['name'] ?? null, 'field' => $work['field'] ?? null,
        'year' => $work['year'], 'cited' => $work['cited'], 'license' => $work['license']];
    $summary['batch'][] = ['uid' => (string) ($paper['uid'] ?? ''), 'status' => 'published',
        'title' => mb_substr((string) $work['title'], 0, 60), 'kb' => (int) round($size / 1024),
        'area' => $area['name'] ?? null, 'field' => $work['field'] ?? null];

    @unlink($temp);
    usleep(200000); // be gentle with the OpenTimestamps calendars
}

$summary['cursor'] = $state['cursor'];
$summary['imported'] = count($state['imported']);
$summary['failed'] = count($state['failed']);
$summary['skipped'] = count($state['skipped']);
$summary['bytes'] = $state['bytes'];
$summary['done'] = $state['cursor'] >= count($state['plan']) || $state['bytes'] >= $budgetBytes;
$summary['log'] = array_slice($state['log'], -5);
$summary['last_failures'] = array_slice($state['failed'], -5);

return $finish($summary);
