<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\Config;
use Athenaeum\Core\Http;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\Timestamp;

/**
 * OpenTimestamps client (pure PHP, no external binaries).
 *
 * Protocol verified against python-opentimestamps and the live calendars:
 *
 *   submit : POST {calendar}/digest
 *            body = raw 32-byte SHA-256 digest
 *            Accept: application/vnd.opentimestamps.v1
 *            Content-Type: application/x-www-form-urlencoded
 *            → raw serialized Timestamp (operation chain)
 *
 *   .ots   : HEADER_MAGIC + varuint(1) + 0x08 + digest + <that response>
 *
 *   upgrade: GET {calendar}/timestamp/{hex(commitment)} where `commitment` is
 *            the message at a PendingAttestation tip, not the file digest.
 *            404 "Pending confirmation in Bitcoin blockchain" means "not yet".
 *
 * The proof file is what makes plagiarism disputes decidable: it commits the
 * exact bytes of the PDF to the Bitcoin blockchain, independently of whether
 * this platform still exists or still hosts the file.
 */
final class OpenTimestamps
{
    public const HEADER_MAGIC = "\x00OpenTimestamps\x00\x00Proof\x00\xbf\x89\xe2\xe8\x84\xe8\x92\x94";

    public const TAG_PENDING = "\x83\xdf\xe3\x0d\x2e\xf9\x0c\x8e";
    public const TAG_BITCOIN = "\x05\x88\x96\x0d\x73\xd7\x19\x01";

    private const OP_APPEND    = "\xf0";
    private const OP_PREPEND   = "\xf1";
    private const OP_REVERSE   = "\xf2";
    private const OP_HEXLIFY   = "\xf3";
    private const OP_SHA1      = "\x02";
    private const OP_RIPEMD160 = "\x03";
    private const OP_SHA256    = "\x08";
    private const OP_KECCAK256 = "\x67";

    private const MAX_MSG_LENGTH = 4096;
    private const MAX_PAYLOAD    = 8192;
    private const MAX_RECURSION  = 256;

    /**
     * The four aggregators the official client stamps to; each one embeds its
     * backing calendar (alice / bob / finney / catallaxy) in the pending
     * attestation it returns.
     */
    public const DEFAULT_CALENDARS = [
        'https://a.pool.opentimestamps.org',
        'https://b.pool.opentimestamps.org',
        'https://a.pool.eternitywall.com',
        'https://ots.btc.catallaxy.com',
    ];

    // =====================================================================
    // Stamping
    // =====================================================================

    /**
     * Create (or reuse) a timestamp proof for one file of a paper.
     *
     * @return array{ok:bool,status:string,timestamp_id?:int,error?:string,commitments?:int}
     */
    public static function stamp(
        int $paperId,
        string $absolutePath,
        string $originalName,
        string $targetType = 'pdf',
        ?int $attachmentId = null
    ): array {
        if (!is_file($absolutePath)) {
            return ['ok' => false, 'status' => 'failed', 'error' => 'file missing'];
        }
        $digest = hash_file('sha256', $absolutePath, true);
        if ($digest === false) {
            return ['ok' => false, 'status' => 'failed', 'error' => 'cannot hash file'];
        }
        $digestHex = bin2hex($digest);

        $existing = Timestamp::findForFile($digestHex, $paperId, $targetType);
        if ($existing !== null) {
            return [
                'ok'           => true,
                'status'       => (string) $existing['status'],
                'timestamp_id' => (int) $existing['id'],
                'commitments'  => (int) $existing['calendar_count'],
            ];
        }

        $now = \Athenaeum\Core\Database::instance()->now();
        $rowId = Timestamp::create([
            'paper_id'      => $paperId,
            'target_type'   => $targetType,
            'attachment_id' => $attachmentId,
            'file_name'     => Str::filename($originalName),
            'file_sha256'   => $digestHex,
            'algo'          => 'sha256',
            'status'        => Timestamp::STATUS_PENDING,
            'attempts'      => 0,
            'created_at'    => $now,
        ]);

        $result = self::submitDigest($digest);
        if (!$result['ok']) {
            Timestamp::update($rowId, [
                'status'        => Timestamp::STATUS_FAILED,
                'last_error'    => mb_substr((string) $result['error'], 0, 480),
                'attempts'      => 1,
                'last_attempt_at' => \Athenaeum\Core\Database::instance()->now(),
            ]);
            Logger::warning('OpenTimestamps submit failed', [
                'paper_id' => $paperId,
                'error'    => $result['error'],
            ]);
            return ['ok' => false, 'status' => 'failed', 'timestamp_id' => $rowId, 'error' => (string) $result['error']];
        }

        $otsBytes = self::buildProof($digest, $result['body']);
        $relative = self::proofRelativePath($paperId, $digestHex);
        $target = Config::path('ots', $relative);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($target, $otsBytes) === false) {
            Timestamp::update($rowId, [
                'status'     => Timestamp::STATUS_FAILED,
                'last_error' => 'cannot write proof file',
            ]);
            return ['ok' => false, 'status' => 'failed', 'timestamp_id' => $rowId, 'error' => 'cannot write proof file'];
        }

        $tree = self::parse($otsBytes);
        $pending = $tree === null ? [] : self::pendingCommitments($tree['root']);

        Timestamp::update($rowId, [
            'status'         => Timestamp::STATUS_PENDING,
            'ots_path'       => $relative,
            'ots_name'       => self::proofFileName($paperId, $digestHex),
            'calendars'      => json_encode($pending, JSON_UNESCAPED_SLASHES),
            'calendar_count' => count($pending),
            'submitted_at'   => \Athenaeum\Core\Database::instance()->now(),
            'last_attempt_at' => \Athenaeum\Core\Database::instance()->now(),
            'attempts'       => 1,
            'last_error'     => null,
        ]);

        Logger::info('OpenTimestamps proof created', [
            'paper_id'    => $paperId,
            'sha256'      => $digestHex,
            'calendars'   => count($pending),
            'proof_bytes' => strlen($otsBytes),
        ]);

        return [
            'ok'           => true,
            'status'       => 'pending',
            'timestamp_id' => $rowId,
            'commitments'  => count($pending),
        ];
    }

    /**
     * Create an OpenTimestamps proof for a content page (关于本站, 投稿指南, …).
     *
     * A page proof commits to a deterministic text snapshot of the page — its
     * slug, title and body in every locale it ships in — rather than to a PDF.
     * The snapshot is stored beside the .ots proof so a visitor can re-hash it
     * and confirm the page has not changed since the moment it was stamped.
     *
     * @return array{ok:bool,status:string,timestamp_id?:int,commitments?:int,error?:string}
     */
    public static function stampPage(int $pageId, string $slug, string $snapshot): array
    {
        if ($snapshot === '') {
            return ['ok' => false, 'status' => 'failed', 'error' => 'empty snapshot'];
        }
        $digest = hash('sha256', $snapshot, true);
        $digestHex = bin2hex($digest);

        $existing = Timestamp::findForPageContent($pageId, $digestHex);
        if ($existing !== null && (string) $existing['status'] !== Timestamp::STATUS_FAILED) {
            return [
                'ok'           => true,
                'status'       => (string) $existing['status'],
                'timestamp_id' => (int) $existing['id'],
                'commitments'  => (int) $existing['calendar_count'],
            ];
        }

        // Store the exact bytes we are about to commit to before contacting any
        // calendar, so verification stays possible even if the calendars fail.
        self::writeSnapshot($pageId, $digestHex, $snapshot);

        $now = \Athenaeum\Core\Database::instance()->now();
        if ($existing !== null) {
            $rowId = (int) $existing['id'];
        } else {
            $rowId = Timestamp::create([
                'paper_id'      => 0,
                'page_id'       => $pageId,
                'target_type'   => Timestamp::TARGET_PAGE,
                'attachment_id' => null,
                'file_name'     => $slug . '.txt',
                'file_sha256'   => $digestHex,
                'algo'          => 'sha256',
                'status'        => Timestamp::STATUS_PENDING,
                'attempts'      => 0,
                'created_at'    => $now,
            ]);
        }

        $result = self::submitDigest($digest);
        if (!$result['ok']) {
            Timestamp::update($rowId, [
                'status'          => Timestamp::STATUS_FAILED,
                'last_error'      => mb_substr((string) $result['error'], 0, 480),
                'attempts'        => (int) ($existing['attempts'] ?? 0) + 1,
                'last_attempt_at' => \Athenaeum\Core\Database::instance()->now(),
            ]);
            Logger::warning('OpenTimestamps page submit failed', [
                'page_id' => $pageId,
                'error'   => $result['error'],
            ]);
            return ['ok' => false, 'status' => 'failed', 'timestamp_id' => $rowId, 'error' => (string) $result['error']];
        }

        $otsBytes = self::buildProof($digest, $result['body']);
        $relative = self::pageProofRelativePath($pageId, $digestHex);
        $target = Config::path('ots', $relative);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (@file_put_contents($target, $otsBytes) === false) {
            Timestamp::update($rowId, [
                'status'     => Timestamp::STATUS_FAILED,
                'last_error' => 'cannot write proof file',
            ]);
            return ['ok' => false, 'status' => 'failed', 'timestamp_id' => $rowId, 'error' => 'cannot write proof file'];
        }

        $tree = self::parse($otsBytes);
        $pending = $tree === null ? [] : self::pendingCommitments($tree['root']);

        Timestamp::update($rowId, [
            'status'          => Timestamp::STATUS_PENDING,
            'ots_path'        => $relative,
            'ots_name'        => self::pageProofFileName($slug),
            'calendars'       => json_encode($pending, JSON_UNESCAPED_SLASHES),
            'calendar_count'  => count($pending),
            'submitted_at'    => \Athenaeum\Core\Database::instance()->now(),
            'last_attempt_at' => \Athenaeum\Core\Database::instance()->now(),
            'attempts'        => (int) ($existing['attempts'] ?? 0) + 1,
            'last_error'      => null,
        ]);

        Logger::info('OpenTimestamps page proof created', [
            'page_id'     => $pageId,
            'slug'        => $slug,
            'sha256'      => $digestHex,
            'calendars'   => count($pending),
            'proof_bytes' => strlen($otsBytes),
        ]);

        return [
            'ok'           => true,
            'status'       => 'pending',
            'timestamp_id' => $rowId,
            'commitments'  => count($pending),
        ];
    }

    /**
     * POST the digest to the configured calendars and merge every successful
     * answer into a single operation chain.
     *
     * @return array{ok:bool,body?:string,error?:string,calendars?:string[]}
     */
    public static function submitDigest(string $digest): array
    {
        $calendars = self::calendars();
        if ($calendars === []) {
            return ['ok' => false, 'error' => 'no calendar configured'];
        }

        $merged = null;
        $used = [];
        $errors = [];

        foreach ($calendars as $calendar) {
            $response = Http::post(
                rtrim($calendar, '/') . '/digest',
                $digest,
                [
                    'Accept'       => 'application/vnd.opentimestamps.v1',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                ['timeout' => (int) Config::get('http.timeout', 20), 'follow' => false]
            );

            if (!$response['ok'] || $response['body'] === '') {
                $errors[] = $calendar . ': ' . ($response['error'] ?? 'empty response');
                continue;
            }
            $node = self::parseNodeFromBody($response['body'], $digest);
            if ($node === null) {
                $errors[] = $calendar . ': unparsable response';
                continue;
            }
            $merged = $merged === null ? $node : self::mergeNodes($merged, $node);
            $used[] = $calendar;
        }

        if ($merged === null) {
            return ['ok' => false, 'error' => implode('; ', $errors) ?: 'all calendars failed'];
        }

        // A single calendar answer is enough; more branches = more redundancy.
        return ['ok' => true, 'body' => self::serializeNode($merged), 'calendars' => $used];
    }

    public static function buildProof(string $digest, string $chainBody): string
    {
        // HEADER_MAGIC + varuint(1) + OpSHA256 tag + digest + serialized timestamp
        return self::HEADER_MAGIC . "\x01" . self::OP_SHA256 . $digest . $chainBody;
    }

    // =====================================================================
    // Upgrading (Bitcoin confirmation)
    // =====================================================================

    /**
     * Try to upgrade every pending proof. Called by bin/ots-upgrade.php (cron)
     * and opportunistically when a paper page is viewed.
     *
     * @return array{checked:int,upgraded:int,failed:int,confirmed:int}
     */
    public static function upgradePending(int $limit = 25, int $minAgeSeconds = 900): array
    {
        $stats = ['checked' => 0, 'upgraded' => 0, 'failed' => 0, 'confirmed' => 0];
        foreach (Timestamp::pendingForUpgrade($limit, $minAgeSeconds) as $row) {
            $stats['checked']++;
            $result = self::upgradeRow($row);
            if ($result['changed']) {
                $stats['upgraded']++;
            }
            if ($result['status'] === Timestamp::STATUS_CONFIRMED) {
                $stats['confirmed']++;
            }
            if ($result['status'] === Timestamp::STATUS_FAILED) {
                $stats['failed']++;
            }
        }
        return $stats;
    }

    /**
     * @param array<string,mixed> $row timestamps row
     * @return array{changed:bool,status:string,error?:string}
     */
    public static function upgradeRow(array $row): array
    {
        $relative = (string) ($row['ots_path'] ?? '');
        if ($relative === '') {
            return ['changed' => false, 'status' => (string) $row['status']];
        }
        $path = Config::path('ots', $relative);
        if (!is_file($path)) {
            Timestamp::update((int) $row['id'], ['last_error' => 'proof file missing']);
            return ['changed' => false, 'status' => (string) $row['status'], 'error' => 'proof file missing'];
        }

        $bytes = (string) file_get_contents($path);
        $tree = self::parse($bytes);
        if ($tree === null) {
            Timestamp::update((int) $row['id'], ['last_error' => 'unparsable proof']);
            return ['changed' => false, 'status' => (string) $row['status'], 'error' => 'unparsable proof'];
        }

        $lastError = null;
        // Merge each calendar's upgraded subtree back in *at the exact node*
        // that carries the pending attestation.
        $changed = self::upgradeNodeInPlace($tree['root'], $lastError);

        $height = self::findBitcoinHeight($tree['root']);
        $pending = self::pendingCommitments($tree['root']);

        if ($changed) {
            @file_put_contents($path, self::serializeProof($tree));
        }

        $status = $height !== null
            ? Timestamp::STATUS_CONFIRMED
            : ($changed ? Timestamp::STATUS_PENDING : (string) $row['status']);

        $update = [
            'status'          => $status,
            'attempts'        => (int) $row['attempts'] + 1,
            'last_attempt_at' => \Athenaeum\Core\Database::instance()->now(),
            'calendars'       => json_encode($pending, JSON_UNESCAPED_SLASHES),
            'calendar_count'  => count($pending),
            'last_error'      => $lastError,
        ];
        if ($changed) {
            $upgradedAt = \Athenaeum\Core\Database::instance()->now();
            $update['upgraded_at'] = $upgradedAt;
            // Only a genuine Bitcoin attestation counts as confirmed.
            if ($height !== null) {
                $update['bitcoin_time'] = self::bitcoinTime($height) ?? $upgradedAt;
            }
        }
        if ($height !== null) {
            $update['bitcoin_height'] = $height;
        }

        Timestamp::update((int) $row['id'], $update);

        if ($status === Timestamp::STATUS_CONFIRMED) {
            $isPage = ((string) ($row['target_type'] ?? 'pdf')) === Timestamp::TARGET_PAGE;
            AuditLog::record(
                'ots.confirmed',
                $isPage ? 'page' : 'paper',
                $isPage ? (int) ($row['page_id'] ?? 0) : (int) $row['paper_id'],
                ['height' => $height, 'sha256' => $row['file_sha256']]
            );
            Logger::info('OpenTimestamps confirmed', [
                'paper_id' => $row['paper_id'],
                'page_id'  => $row['page_id'] ?? null,
                'height'   => $height,
            ]);
        }

        return ['changed' => $changed, 'status' => $status, 'error' => $lastError ?? undefined_or_null()];
    }

    /** Rebuild a complete .ots byte string from a parsed tree. */
    public static function serializeProof(array $tree): string
    {
        return self::HEADER_MAGIC . "\x01" . $tree['file_hash_op'] . $tree['digest'] . self::serializeNode($tree['root']);
    }

    /**
     * Ask every calendar referenced by a PendingAttestation for an upgraded
     * subtree and merge the answers back in place.
     *
     * The commitment to query is the message of the node the attestation sits
     * on — not the file digest — which is why this walks the tree instead of
     * using a flat list.
     *
     * @param array<string,mixed> $node      modified by reference
     * @param string|null         $lastError filled with the last transport error
     * @return bool true when at least one subtree was merged
     */
    public static function upgradeNodeInPlace(array &$node, ?string &$lastError = null): bool
    {
        $changed = false;
        $commitment = (string) $node['msg'];

        // Snapshot: merging leaves the pending attestation in place.
        foreach (array_values($node['attestations']) as $attestation) {
            if ($attestation['tag'] !== self::TAG_PENDING) {
                continue;
            }
            $uri = self::pendingUri($attestation['payload']);
            if ($uri === null) {
                continue;
            }

            $response = Http::get(
                rtrim($uri, '/') . '/timestamp/' . bin2hex($commitment),
                [
                    'Accept'     => 'application/vnd.opentimestamps.v1',
                    'User-Agent' => (string) Config::get('http.user_agent', 'AthenXiv'),
                ],
                ['timeout' => (int) Config::get('http.timeout', 20), 'follow' => false]
            );

            if ($response['status'] === 404) {
                // "Pending confirmation in Bitcoin blockchain" or "Not found":
                // both mean "nothing to merge yet".
                continue;
            }
            if (!$response['ok'] || $response['body'] === '') {
                $lastError = $response['error'] ?? 'upgrade request failed';
                continue;
            }
            $incoming = self::parseNodeFromBody($response['body'], $commitment);
            if ($incoming === null) {
                $lastError = 'unparsable upgrade response';
                continue;
            }
            $node = self::mergeNodes($node, $incoming);
            $changed = true;
        }

        foreach ($node['ops'] as $index => $op) {
            if (self::upgradeNodeInPlace($node['ops'][$index]['child'], $lastError)) {
                $changed = true;
            }
        }

        return $changed;
    }

    /** Calendar URI inside a PendingAttestation payload, or null. */
    private static function pendingUri(string $payload): ?string
    {
        try {
            [$uri] = self::readVarBytes($payload, 0, self::MAX_PAYLOAD);
        } catch (\Throwable) {
            return null;
        }
        $uri = trim($uri);
        return $uri === '' ? null : $uri;
    }

    /** Bitcoin block timestamp via a public explorer; failures are harmless. */
    public static function bitcoinTime(int $height, ?string $explorer = null): ?string
    {
        $explorer = rtrim($explorer ?? 'https://blockstream.info/api', '/');
        $hash = Http::get($explorer . '/block-height/' . $height, [], ['timeout' => 12]);
        if (!$hash['ok'] || trim($hash['body']) === '') {
            return null;
        }
        $block = Http::get($explorer . '/block/' . trim($hash['body']), [], ['timeout' => 12]);
        if (!$block['ok']) {
            return null;
        }
        $decoded = json_decode($block['body'], true);
        if (!is_array($decoded) || empty($decoded['timestamp'])) {
            return null;
        }
        return gmdate('Y-m-d H:i:s', (int) $decoded['timestamp']);
    }

    // =====================================================================
    // Proof storage helpers
    // =====================================================================

    public static function proofRelativePath(int $paperId, string $digestHex): string
    {
        return 'p' . $paperId . '/' . gmdate('Ymd') . '-' . substr($digestHex, 0, 16) . '.ots';
    }

    public static function proofFileName(int $paperId, string $digestHex): string
    {
        return 'paper-' . $paperId . '-' . substr($digestHex, 0, 12) . '.ots';
    }

    /** Proof path for a content page proof (kept apart from paper proofs). */
    public static function pageProofRelativePath(int $pageId, string $digestHex): string
    {
        return 'pages/p' . $pageId . '/' . gmdate('Ymd') . '-' . substr($digestHex, 0, 16) . '.ots';
    }

    public static function pageProofFileName(string $slug): string
    {
        return 'page-' . $slug . '.ots';
    }

    public static function snapshotFileName(string $slug): string
    {
        return 'page-' . $slug . '.txt';
    }

    /**
     * The snapshot shares the proof's path with a .txt extension, so it can be
     * found again later without storing a second path in the database.
     */
    public static function snapshotPathFor(array|string $proof): string
    {
        $relative = is_array($proof) ? (string) ($proof['ots_path'] ?? '') : (string) $proof;
        return (string) preg_replace('/\.ots$/', '.txt', $relative);
    }

    private static function writeSnapshot(int $pageId, string $digestHex, string $snapshot): void
    {
        $path = Config::path('ots', self::snapshotPathFor(self::pageProofRelativePath($pageId, $digestHex)));
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, $snapshot);
    }

    public static function verifyUrl(): string
    {
        $configured = Settings::string('ots.verify_url');
        return $configured !== '' ? $configured : 'https://opentimestamps.org/#stamp-and-verify';
    }

    /** @return string[] */
    public static function calendars(): array
    {
        $configured = Settings::list('ots.calendars');
        return $configured === [] ? self::DEFAULT_CALENDARS : $configured;
    }

    /** Human readable summary for the paper page. */
    public static function describe(array $timestamp): array
    {
        $status = (string) $timestamp['status'];
        return [
            'status'      => $status,
            'label'       => Timestamp::statusLabel($status),
            'sha256'      => (string) $timestamp['file_sha256'],
            'submitted'   => $timestamp['submitted_at'],
            'confirmed'   => $timestamp['bitcoin_time'] ?? $timestamp['upgraded_at'],
            'height'      => $timestamp['bitcoin_height'] !== null ? (int) $timestamp['bitcoin_height'] : null,
            'verify_url'  => self::verifyUrl(),
            'proof_url'   => $timestamp['ots_path'] ? url('paper.timestamp.download', [
                'uid'       => (string) ($timestamp['paper_uid'] ?? ''),
                'timestamp' => (int) $timestamp['id'],
            ]) : null,
        ];
    }

    // =====================================================================
    // Serialization primitives
    // =====================================================================

    public static function writeVarUint(int $value): string
    {
        $out = '';
        while (true) {
            $byte = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $out .= chr($byte | 0x80);
            } else {
                $out .= chr($byte);
                break;
            }
        }
        return $out;
    }

    public static function writeVarBytes(string $bytes): string
    {
        return self::writeVarUint(strlen($bytes)) . $bytes;
    }

    /** @return array{0:string,1:int} value + new offset */
    private static function readVarUint(string $data, int $offset): array
    {
        $value = 0;
        $shift = 0;
        $length = strlen($data);
        while (true) {
            if ($offset >= $length) {
                throw new \RuntimeException('OTS: truncated varuint');
            }
            $byte = ord($data[$offset]);
            $offset++;
            $value |= ($byte & 0x7F) << $shift;
            if (($byte & 0x80) === 0) {
                break;
            }
            $shift += 7;
            if ($shift > 63) {
                throw new \RuntimeException('OTS: varuint too large');
            }
        }
        return [$value, $offset];
    }

    /** @return array{0:string,1:int} */
    private static function readVarBytes(string $data, int $offset, int $maxLength): array
    {
        [$length, $offset] = self::readVarUint($data, $offset);
        if ($length > $maxLength) {
            throw new \RuntimeException('OTS: varbytes exceeds limit');
        }
        if ($offset + $length > strlen($data)) {
            throw new \RuntimeException('OTS: truncated varbytes');
        }
        return [substr($data, $offset, $length), $offset + $length];
    }

    // =====================================================================
    // Parsing
    // =====================================================================

    /**
     * Parse a detached timestamp file.
     *
     * @return array{file_hash_op:string,digest:string,root:array}|null
     */
    public static function parse(string $bytes): ?array
    {
        try {
            if (!str_starts_with($bytes, self::HEADER_MAGIC)) {
                return null;
            }
            $offset = strlen(self::HEADER_MAGIC);
            [$version, $offset] = self::readVarUint($bytes, $offset);
            if ($version !== 1) {
                return null;
            }
            if ($offset >= strlen($bytes)) {
                return null;
            }
            $fileHashOp = $bytes[$offset];
            $offset++;
            $digestLength = self::digestLengthForOp($fileHashOp);
            if ($digestLength === null || $offset + $digestLength > strlen($bytes)) {
                return null;
            }
            $digest = substr($bytes, $offset, $digestLength);
            $offset += $digestLength;

            $root = self::parseNode($bytes, $offset, $digest, self::MAX_RECURSION);

            return ['file_hash_op' => $fileHashOp, 'digest' => $digest, 'root' => $root['node']];
        } catch (\Throwable $e) {
            Logger::warning('OTS parse failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Parse a bare calendar answer (operation chain) for a known message. */
    public static function parseNodeFromBody(string $body, string $msg): ?array
    {
        try {
            $parsed = self::parseNode($body, 0, $msg, self::MAX_RECURSION);
            return $parsed['node'];
        } catch (\Throwable $e) {
            Logger::warning('OTS chain parse failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array{node:array,offset:int}
     */
    private static function parseNode(string $data, int $offset, string $msg, int $depth): array
    {
        if ($depth <= 0) {
            throw new \RuntimeException('OTS: recursion limit');
        }
        $node = ['msg' => $msg, 'attestations' => [], 'ops' => []];
        $length = strlen($data);
        if ($offset >= $length) {
            throw new \RuntimeException('OTS: truncated timestamp');
        }

        $tag = $data[$offset];
        $offset++;
        while ($tag === "\xff") {
            if ($offset >= $length) {
                throw new \RuntimeException('OTS: truncated timestamp (tag)');
            }
            $sub = $data[$offset];
            $offset++;
            $offset = self::parseEntry($data, $offset, $sub, $msg, $node, $depth);
            if ($offset >= $length) {
                throw new \RuntimeException('OTS: truncated timestamp (tail)');
            }
            $tag = $data[$offset];
            $offset++;
        }
        $offset = self::parseEntry($data, $offset, $tag, $msg, $node, $depth);

        return ['node' => $node, 'offset' => $offset];
    }

    private static function parseEntry(
        string $data,
        int $offset,
        string $tag,
        string $msg,
        array &$node,
        int $depth
    ): int {
        if ($tag === "\x00") {
            // attestation: 8 byte tag + varbytes(payload)
            if ($offset + 8 > strlen($data)) {
                throw new \RuntimeException('OTS: truncated attestation tag');
            }
            $attestationTag = substr($data, $offset, 8);
            $offset += 8;
            [$payload, $offset] = self::readVarBytes($data, $offset, self::MAX_PAYLOAD);
            $node['attestations'][] = ['tag' => $attestationTag, 'payload' => $payload];
            return $offset;
        }

        [$opTag, $nextOffset, $op] = self::applyOp($tag, $data, $offset, $msg);
        $child = self::parseNode($data, $nextOffset, $op['msg'], $depth - 1);
        $node['ops'][] = [
            'tag'   => $opTag,
            'arg'   => $op['arg'],
            'child' => $child['node'],
        ];
        return $child['offset'];
    }

    /**
     * Apply one op tag.
     *
     * @return array{0:string,1:int,2:array{msg:string,arg:?string}} tag, offset after the op, result
     */
    private static function applyOp(string $tag, string $data, int $offset, string $msg): array
    {
        switch ($tag) {
            case self::OP_APPEND:
                [$arg, $offset] = self::readVarBytes($data, $offset, self::MAX_MSG_LENGTH);
                return [$tag, $offset, ['msg' => $msg . $arg, 'arg' => $arg]];
            case self::OP_PREPEND:
                [$arg, $offset] = self::readVarBytes($data, $offset, self::MAX_MSG_LENGTH);
                return [$tag, $offset, ['msg' => $arg . $msg, 'arg' => $arg]];
            case self::OP_REVERSE:
                return [$tag, $offset, ['msg' => strrev($msg), 'arg' => null]];
            case self::OP_HEXLIFY:
                return [$tag, $offset, ['msg' => bin2hex($msg), 'arg' => null]];
            case self::OP_SHA1:
                return [$tag, $offset, ['msg' => hash('sha1', $msg, true), 'arg' => null]];
            case self::OP_RIPEMD160:
                return [$tag, $offset, ['msg' => hash('ripemd160', $msg, true), 'arg' => null]];
            case self::OP_SHA256:
                return [$tag, $offset, ['msg' => hash('sha256', $msg, true), 'arg' => null]];
            default:
                throw new \RuntimeException(sprintf('OTS: unsupported op tag 0x%02x', ord($tag)));
        }
    }

    private static function digestLengthForOp(string $tag): ?int
    {
        return match ($tag) {
            self::OP_SHA1, self::OP_RIPEMD160 => 20,
            self::OP_SHA256, self::OP_KECCAK256 => 32,
            default => null,
        };
    }

    // =====================================================================
    // Serializing a tree
    // =====================================================================

    public static function serializeNode(array $node): string
    {
        $attestations = $node['attestations'];
        $ops = $node['ops'];
        if ($attestations === [] && $ops === []) {
            throw new \RuntimeException('OTS: refusing to serialize an empty timestamp');
        }

        // Same ordering rules as python-opentimestamps: attestations by tag,
        // ops with unary ops first (stable, so parse order is preserved).
        usort($attestations, static fn (array $a, array $b): int => strcmp($a['tag'], $b['tag']));
        $ops = self::stableSortOps($ops);

        $out = '';
        $attestationCount = count($attestations);
        $opCount = count($ops);

        if ($attestationCount > 1) {
            for ($i = 0; $i < $attestationCount - 1; $i++) {
                $out .= "\xff\x00" . self::serializeAttestation($attestations[$i]);
            }
        }

        if ($opCount === 0) {
            $out .= "\x00" . self::serializeAttestation($attestations[$attestationCount - 1]);
            return $out;
        }

        if ($attestationCount > 0) {
            $out .= "\xff\x00" . self::serializeAttestation($attestations[$attestationCount - 1]);
        }
        for ($i = 0; $i < $opCount - 1; $i++) {
            $out .= "\xff" . self::serializeOp($ops[$i]) . self::serializeNode($ops[$i]['child']);
        }
        $last = $ops[$opCount - 1];
        $out .= self::serializeOp($last) . self::serializeNode($last['child']);

        return $out;
    }

    private static function serializeOp(array $op): string
    {
        return $op['arg'] === null ? $op['tag'] : $op['tag'] . self::writeVarBytes($op['arg']);
    }

    private static function serializeAttestation(array $attestation): string
    {
        return $attestation['tag'] . self::writeVarBytes($attestation['payload']);
    }

    /** Unary ops (no argument) sort before binary ops, then by tag byte. */
    private static function stableSortOps(array $ops): array
    {
        $decorated = [];
        foreach ($ops as $index => $op) {
            $decorated[] = ['op' => $op, 'index' => $index, 'class' => $op['arg'] === null ? 0 : 1];
        }
        usort($decorated, static function (array $a, array $b): int {
            if ($a['class'] !== $b['class']) {
                return $a['class'] <=> $b['class'];
            }
            if ($a['class'] === 1) {
                $cmp = strcmp((string) $a['op']['arg'], (string) $b['op']['arg']);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return $a['index'] <=> $b['index'];
        });
        return array_map(static fn (array $item): array => $item['op'], $decorated);
    }

    // =====================================================================
    // Tree operations
    // =====================================================================

    /** Union of two trees that share the same message. */
    public static function mergeNodes(array $a, array $b): array
    {
        if ($a['msg'] !== $b['msg']) {
            throw new \RuntimeException('OTS: cannot merge timestamps for different messages');
        }
        $node = ['msg' => $a['msg'], 'attestations' => $a['attestations'], 'ops' => $a['ops']];

        foreach ($b['attestations'] as $attestation) {
            $exists = false;
            foreach ($node['attestations'] as $existing) {
                if ($existing['tag'] === $attestation['tag'] && $existing['payload'] === $attestation['payload']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $node['attestations'][] = $attestation;
            }
        }

        foreach ($b['ops'] as $op) {
            $merged = false;
            foreach ($node['ops'] as $index => $existing) {
                if ($existing['tag'] === $op['tag'] && $existing['arg'] === $op['arg']) {
                    $node['ops'][$index]['child'] = self::mergeNodes($existing['child'], $op['child']);
                    $merged = true;
                    break;
                }
            }
            if (!$merged) {
                $node['ops'][] = $op;
            }
        }

        return $node;
    }

    /**
     * All PendingAttestation tips: the commitments that must be queried on
     * their calendar to obtain the Bitcoin attestation.
     *
     * @return array<int,array{commitment:string,uri:string,payload:string}>
     */
    public static function pendingCommitments(array $node): array
    {
        $out = [];
        self::collectPending($node, $out);
        return $out;
    }

    private static function collectPending(array $node, array &$out): void
    {
        foreach ($node['attestations'] as $attestation) {
            if ($attestation['tag'] !== self::TAG_PENDING) {
                continue;
            }
            $payload = $attestation['payload'];
            try {
                // A PendingAttestation records nothing but the calendar URI.
                [$uri, $offset] = self::readVarBytes($payload, 0, self::MAX_PAYLOAD);
                $extra = '';
                if ($offset < strlen($payload)) {
                    [$extra] = self::readVarBytes($payload, $offset, self::MAX_PAYLOAD);
                }
            } catch (\Throwable) {
                continue;
            }
            $out[] = [
                'commitment' => $node['msg'],
                'uri'        => $uri,
                'payload'    => bin2hex($extra),
            ];
        }
        foreach ($node['ops'] as $op) {
            self::collectPending($op['child'], $out);
        }
    }

    /** Highest Bitcoin block height attested anywhere in the tree. */
    public static function findBitcoinHeight(array $node): ?int
    {
        $height = null;
        foreach ($node['attestations'] as $attestation) {
            if ($attestation['tag'] !== self::TAG_BITCOIN) {
                continue;
            }
            try {
                [$value] = self::readVarUint($attestation['payload'], 0);
                $height = $height === null ? $value : max($height, $value);
            } catch (\Throwable) {
                continue;
            }
        }
        foreach ($node['ops'] as $op) {
            $child = self::findBitcoinHeight($op['child']);
            if ($child !== null) {
                $height = $height === null ? $child : max($height, $child);
            }
        }
        return $height;
    }

    /** Flat list of attestations, for diagnostics and the admin panel. */
    public static function attestations(array $node): array
    {
        $out = [];
        foreach ($node['attestations'] as $attestation) {
            $label = match ($attestation['tag']) {
                self::TAG_PENDING => 'pending',
                self::TAG_BITCOIN => 'bitcoin',
                default           => 'unknown:' . bin2hex($attestation['tag']),
            };
            $out[] = ['type' => $label, 'payload' => bin2hex($attestation['payload'])];
        }
        foreach ($node['ops'] as $op) {
            $out = array_merge($out, self::attestations($op['child']));
        }
        return $out;
    }
}

/** Guard for optional values without importing null coalescing noise. */
function undefined_or_null(): ?string
{
    return null;
}
