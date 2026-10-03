<?php
/**
 * AthenXiv installer.
 *
 *   php bin/install.php                       # interactive admin account
 *   php bin/install.php --email=a@b.c --password=... --nickname=Keeper
 *   php bin/install.php --driver=sqlite       # override the configured driver
 *   php bin/install.php --fresh               # drop existing tables first
 *
 * Works for both MySQL (production) and SQLite (local verification).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Category;
use Athenaeum\Models\Section;
use Athenaeum\Models\User;

Config::load($config);

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $argument, $matches)) {
        $options[$matches[1]] = $matches[2] ?? true;
    }
}

if (!empty($options['driver'])) {
    Config::set('db.driver', (string) $options['driver']);
}
if (!empty($options['sqlite-path'])) {
    Config::set('db.sqlite_path', (string) $options['sqlite-path']);
}

$driver = (string) Config::get('db.driver', 'mysql');
echo "AthenXiv installer\n";
echo "  driver : {$driver}\n";
if ($driver === 'sqlite') {
    echo '  file   : ' . Config::get('db.sqlite_path') . "\n";
} else {
    echo '  host   : ' . Config::get('db.host') . ':' . Config::get('db.port') . "\n";
    echo '  db     : ' . Config::get('db.database') . "\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// 1. Connect
// ---------------------------------------------------------------------------
try {
    $db = Database::instance();
    $db->pdo()->query('SELECT 1');
} catch (Throwable $e) {
    fwrite(STDERR, "Cannot connect to the database:\n  " . $e->getMessage() . "\n\n");
    fwrite(STDERR, "If the MySQL server is unreachable, verify locally with:\n");
    fwrite(STDERR, "  php bin/install.php --driver=sqlite\n");
    exit(1);
}
echo "[ok] connected\n";

// ---------------------------------------------------------------------------
// 2. Schema
// ---------------------------------------------------------------------------
$schemaFile = $root . '/database/schema.' . ($driver === 'sqlite' ? 'sqlite' : 'mysql') . '.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Missing schema file: {$schemaFile}\n");
    exit(1);
}
$schema = (string) file_get_contents($schemaFile);
$schema = str_replace('{prefix}', (string) Config::get('db.prefix', ''), $schema);

if (!empty($options['fresh'])) {
    $tables = [
        'timestamps', 'paper_links', 'attachments', 'paper_authors', 'paper_versions', 'papers',
        'categories', 'sections', 'user_links', 'users', 'settings', 'audit_logs', 'login_attempts',
        'pages', 'email_verifications',
    ];
    foreach (array_reverse($tables) as $table) {
        try {
            $db->pdo()->exec('DROP TABLE IF EXISTS ' . $db->quoteIdentifier($db->table($table)));
        } catch (Throwable $e) {
            fwrite(STDERR, "  ! could not drop {$table}: {$e->getMessage()}\n");
        }
    }
    echo "[ok] dropped existing tables (--fresh)\n";
}

// Strip standalone comment lines BEFORE splitting: a statement that happens to
// be preceded by a `-- …` banner would otherwise be skipped entirely, which
// silently leaves tables out of a fresh install.
$schema = (string) preg_replace('/^[ \t]*--.*$/m', '', $schema);
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $schema) ?: []));
$created = 0;
foreach ($statements as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')
        || (str_starts_with($statement, 'SET ') && strlen($statement) < 40)) {
        continue;
    }
    try {
        $db->pdo()->exec($statement);
        $created++;
    } catch (Throwable $e) {
        fwrite(STDERR, "  ! statement failed: " . substr($statement, 0, 70) . "…\n    {$e->getMessage()}\n");
    }
}
echo "[ok] schema applied ({$created} statements)\n";

// ---------------------------------------------------------------------------
// 3. Settings
// ---------------------------------------------------------------------------
foreach (Settings::DEFAULTS as $key => $value) {
    Settings::set($key, $value);
}
Settings::flush();
echo '[ok] ' . count(Settings::DEFAULTS) . " settings written\n";

// ---------------------------------------------------------------------------
// 4. Sections (分区) — the example tiers requested for this platform
// ---------------------------------------------------------------------------
Section::createSection('preprints', [
    'zh-CN' => '预印本', 'en' => 'Preprints', 'ja' => 'プレプリント',
    'ko' => '프리프린트', 'fr' => 'Prépublications', 'de' => 'Preprints',
], [
    'zh-CN' => '尚未正式发表、但已经完成时间戳存证的稿件。',
    'en'    => 'Work that is not formally published yet, but is already timestamped.',
    'ja'    => '未公刊だがタイムスタンプ済みの原稿。',
    'ko'    => '아직 정식 게재되지 않았지만 타임스탬프가 찍힌 원고.',
    'fr'    => 'Travaux non encore publiés, mais déjà horodatés.',
    'de'    => 'Noch nicht förmlich veröffentlichte, aber bereits zeitgestempelte Arbeiten.',
], 10, true);

Section::createSection('tier-1', [
    'zh-CN' => '一区论文', 'en' => 'Tier 1 papers', 'ja' => '第1区論文',
    'ko' => '1군 논문', 'fr' => 'Articles de rang 1', 'de' => 'Rang-1-Aufsätze',
], [
    'zh-CN' => '编辑评定为最高一档的论文。',
    'en'    => 'Papers the editors rate in the highest band.',
    'ja'    => '編集部が最上位と評価した論文。',
    'ko'    => '편집부가 최상위로 평가한 논문.',
    'fr'    => 'Articles classés dans la catégorie la plus haute par les éditeurs.',
    'de'    => 'Von der Redaktion am höchsten eingestufte Aufsätze.',
], 20);

Section::createSection('tier-2', [
    'zh-CN' => '二区论文', 'en' => 'Tier 2 papers', 'ja' => '第2区論文',
    'ko' => '2군 논문', 'fr' => 'Articles de rang 2', 'de' => 'Rang-2-Aufsätze',
], [
    'zh-CN' => '编辑评定为第二档的论文。',
    'en'    => 'Papers the editors rate in the second band.',
], 30);

Section::createSection('tier-3', [
    'zh-CN' => '三区论文', 'en' => 'Tier 3 papers', 'ja' => '第3区論文',
    'ko' => '3군 논문', 'fr' => 'Articles de rang 3', 'de' => 'Rang-3-Aufsätze',
], [
    'zh-CN' => '编辑评定为第三档的论文。',
    'en'    => 'Papers the editors rate in the third band.',
], 40);
echo "[ok] 4 sections created\n";

// ---------------------------------------------------------------------------
// 5. Subject areas (分类)
// ---------------------------------------------------------------------------
$categories = [
    ['metaphysics', ['zh-CN' => '形而上学', 'en' => 'Metaphysics', 'ja' => '形而上学', 'ko' => '형이상학', 'fr' => 'Métaphysique', 'de' => 'Metaphysik']],
    ['epistemology', ['zh-CN' => '认识论', 'en' => 'Epistemology', 'ja' => '認識論', 'ko' => '인식론', 'fr' => 'Épistémologie', 'de' => 'Erkenntnistheorie']],
    ['ethics', ['zh-CN' => '伦理学', 'en' => 'Ethics', 'ja' => '倫理学', 'ko' => '윤리학', 'fr' => 'Éthique', 'de' => 'Ethik']],
    ['logic', ['zh-CN' => '逻辑学', 'en' => 'Logic', 'ja' => '論理学', 'ko' => '논리학', 'fr' => 'Logique', 'de' => 'Logik']],
    ['aesthetics', ['zh-CN' => '美学', 'en' => 'Aesthetics', 'ja' => '美学', 'ko' => '미학', 'fr' => 'Esthétique', 'de' => 'Ästhetik']],
    ['political-philosophy', ['zh-CN' => '政治哲学', 'en' => 'Political philosophy', 'ja' => '政治哲学', 'ko' => '정치철학', 'fr' => 'Philosophie politique', 'de' => 'Politische Philosophie']],
    ['phenomenology', ['zh-CN' => '现象学', 'en' => 'Phenomenology', 'ja' => '現象学', 'ko' => '현상학', 'fr' => 'Phénoménologie', 'de' => 'Phänomenologie']],
    ['philosophy-of-mind', ['zh-CN' => '心灵哲学', 'en' => 'Philosophy of mind', 'ja' => '心の哲学', 'ko' => '심리철학', 'fr' => 'Philosophie de l’esprit', 'de' => 'Philosophie des Geistes']],
    ['philosophy-of-science', ['zh-CN' => '科学哲学', 'en' => 'Philosophy of science', 'ja' => '科学哲学', 'ko' => '과학철학', 'fr' => 'Philosophie des sciences', 'de' => 'Wissenschaftsphilosophie']],
    ['chinese-philosophy', ['zh-CN' => '中国哲学', 'en' => 'Chinese philosophy', 'ja' => '中国哲学', 'ko' => '중국철학', 'fr' => 'Philosophie chinoise', 'de' => 'Chinesische Philosophie']],
    ['history-of-philosophy', ['zh-CN' => '哲学史', 'en' => 'History of philosophy', 'ja' => '哲学史', 'ko' => '철학사', 'fr' => 'Histoire de la philosophie', 'de' => 'Philosophiegeschichte']],
    ['philosophy-of-religion', ['zh-CN' => '宗教哲学', 'en' => 'Philosophy of religion', 'ja' => '宗教哲学', 'ko' => '종교철학', 'fr' => 'Philosophie de la religion', 'de' => 'Religionsphilosophie']],
];
$order = 0;
foreach ($categories as [$slug, $names]) {
    if (Category::findBySlug($slug) !== null) {
        continue;
    }
    Category::create([
        'slug'       => $slug,
        'names'      => json_encode($names, JSON_UNESCAPED_UNICODE),
        'sort_order' => $order += 10,
        'created_at' => $db->now(),
    ]);
}
echo '[ok] ' . count($categories) . " subject areas created\n";

// ---------------------------------------------------------------------------
// 6. Administrator account
// ---------------------------------------------------------------------------
$email = (string) ($options['email'] ?? '');
$password = (string) ($options['password'] ?? '');
$nickname = (string) ($options['nickname'] ?? '');

if ($email === '' && PHP_SAPI === 'cli' && stream_isatty(STDIN)) {
    echo "\nAdministrator account\n";
    $email = trim((string) readline('  e-mail   : '));
    if ($email !== '') {
        $password = (string) readline('  password : ');
        $nickname = trim((string) readline('  nickname : '));
    }
}

$admin = null;
if ($email !== '') {
    if (mb_strlen($password) < 10) {
        fwrite(STDERR, "[!] password must be at least 10 characters; admin account not created\n");
    } else {
        $existing = User::findByEmail($email);
        if ($existing !== null) {
            User::update((int) $existing['id'], ['role' => 'admin']);
            if ($password !== '') {
                User::updatePassword((int) $existing['id'], $password);
            }
            $admin = User::find((int) $existing['id']);
            echo "[ok] existing account promoted to admin: {$email}\n";
        } else {
            $id = User::createByAdmin([
                'email'        => $email,
                'password'     => $password,
                'nickname'     => $nickname !== '' ? $nickname : 'Administrator',
                'display_name' => $nickname !== '' ? $nickname : 'Administrator',
                'role'         => 'admin',
                'status'       => 'active',
            ]);
            $admin = User::find($id);
            echo "[ok] administrator created: {$email}\n";
        }
    }
}

if ($admin === null) {
    echo "[!] no administrator account yet — create one later with:\n";
    echo "    php bin/create-admin.php --email=you@example.org --password=... --nickname=You\n";
} else {
    echo "     uid: {$admin['uid']}\n";
}

// ---------------------------------------------------------------------------
// 7. Summary
// ---------------------------------------------------------------------------
Settings::flush();
echo "\nDone.\n";
echo "  site name   : " . Settings::siteName() . "\n";
echo "  sections    : " . count(Section::ordered()) . "\n";
echo "  categories  : " . count(Category::ordered()) . "\n";
echo "\nNext steps:\n";
echo "  1. point your web root at  " . $root . DIRECTORY_SEPARATOR . "public\n";
echo "  2. adjust upload_max_filesize / post_max_size in php.ini if you need bigger PDFs\n";
echo "  3. schedule the timestamp upgrade: php bin/ots-upgrade.php --limit=50\n";
Logger::info('installer finished', ['driver' => $driver]);
