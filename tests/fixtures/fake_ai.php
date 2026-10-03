<?php
/**
 * Mock OpenAI-compatible endpoint for offline AI-review tests.
 *
 *   php -S 127.0.0.1:8198 tests/fixtures/fake_ai.php
 *
 * GET  /v1/models            → a model list (for the admin "test connection")
 * POST /v1/chat/completions  → a fixed JSON verdict, and the received request is
 *                              written to /tmp so the test can inspect the prompt.
 *
 * Configure with environment variables:
 *   FAKE_AI_DECISION=approve|reject|review   FAKE_AI_CONFIDENCE=87
 *   FAKE_AI_CATEGORY=metaphysics             FAKE_AI_SECTION=preprints
 *   FAKE_AI_MODE=verdict|malformed|error|http500
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decision = getenv('FAKE_AI_DECISION') ?: 'approve';
$confidence = (int) (getenv('FAKE_AI_CONFIDENCE') ?: 87);
$category = getenv('FAKE_AI_CATEGORY') ?: 'metaphysics';
$section = getenv('FAKE_AI_SECTION') ?: 'preprints';
$mode = getenv('FAKE_AI_MODE') ?: 'verdict';

$requestFile = sys_get_temp_dir() . '/fake-ai-last-request.json';

if ($path === '/v1/models' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'object' => 'list',
        'data'   => [
            ['id' => 'test-model-a', 'object' => 'model'],
            ['id' => 'test-model-b', 'object' => 'model'],
        ],
    ]);
    return;
}

if ($path === '/v1/chat/completions' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = (string) file_get_contents('php://input');
    @file_put_contents($requestFile, $raw);
    $payload = json_decode($raw, true);
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if ($mode === 'http500') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => ['message' => 'simulated provider outage']]);
        return;
    }

    if ($mode === 'error') {
        header('Content-Type: application/json');
        echo json_encode(['error' => ['message' => 'insufficient quota']]);
        return;
    }

    $verdict = [
        'decision'      => $decision,
        'confidence'    => $confidence,
        'reason'        => 'The submission is legible, self-contained and argues for a clear thesis; '
            . 'the references are consistent with the abstract.',
        'category_slug' => $category,
        'section_slug'  => $section,
        'tags'          => ['epistemology', 'test-fixture'],
    ];

    // FAKE_AI_NEW_AREA=name renders a proposal for an area that does not exist
    // yet, which exercises the "the AI may create a subject area" path.
    $newArea = getenv('FAKE_AI_NEW_AREA') ?: '';
    if ($newArea !== '') {
        $verdict['category_slug'] = null;
        $verdict['category_new'] = [
            'slug'        => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $newArea) ?: 'new-area'),
            'name'        => $newArea,
            'name_zh'     => '',
            'parent_slug' => getenv('FAKE_AI_NEW_AREA_PARENT') ?: null,
        ];
    }

    $content = $mode === 'malformed'
        ? "Here is my assessment:\n\n" . json_encode($verdict) . "\n\nI hope this helps!"
        : json_encode($verdict);

    header('Content-Type: application/json');
    echo json_encode([
        'id'      => 'chatcmpl-fixture',
        'model'   => $payload['model'] ?? 'test-model',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
        'usage'   => ['prompt_tokens' => 1234, 'completion_tokens' => 56, 'total_tokens' => 1290],
        'echo'    => ['authorization' => $auth !== '' ? 'present' : 'missing'],
    ]);
    return;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => ['message' => 'not found']]);
