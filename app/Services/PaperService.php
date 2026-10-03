<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\App;
use Athenaeum\Core\Auth;
use Athenaeum\Core\Config;
use Athenaeum\Core\Database;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Str;
use Athenaeum\Models\Attachment;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\Category;
use Athenaeum\Models\Paper;
use Athenaeum\Models\PaperAuthor;
use Athenaeum\Models\User;
use Athenaeum\Models\PaperLink;
use Athenaeum\Models\PaperVersion;
use Athenaeum\Models\Section;

/**
 * All paper lifecycle rules live here so controllers stay thin: creation,
 * edits, submission, withdrawal, moderation and admin proxy uploads.
 */
final class PaperService
{
    /**
     * Limits for the current context. Admins performing a proxy upload may
     * exceed them ("破例"), which is recorded on the paper.
     *
     * @return array{pdf:int,attachment:int,attachments:int,exempt:bool}
     */
    public static function limits(bool $adminExempt = false): array
    {
        $pdfMb = Settings::int('upload.max_pdf_mb', 10);
        $attachmentMb = Settings::int('upload.max_attachment_mb', 20);
        $phpLimit = Uploader::phpUploadLimit();

        $pdf = $pdfMb * 1024 * 1024;
        $attachment = $attachmentMb * 1024 * 1024;

        if ($adminExempt) {
            // "破例" means: ignore the application level cap if PHP itself
            // allows the byte count, otherwise fall back to PHP's limit.
            $pdf = $phpLimit > 0 ? $phpLimit : PHP_INT_MAX;
            $attachment = $phpLimit > 0 ? $phpLimit : PHP_INT_MAX;
        } else {
            if ($phpLimit > 0) {
                $pdf = min($pdf, $phpLimit);
                $attachment = min($attachment, $phpLimit);
            }
        }

        return [
            'pdf'         => $pdf,
            'attachment'  => $attachment,
            'attachments' => Settings::int('upload.max_attachments', 5),
            'exempt'      => $adminExempt,
        ];
    }

    /** Human readable hint shown next to the PDF field. */
    public static function pdfLimitMessage(bool $adminExempt = false): string
    {
        $custom = Settings::string('upload.pdf_message');
        if ($custom !== '') {
            return $custom;
        }
        $limits = self::limits($adminExempt);
        return __('upload.pdf_limit_hint', [
            'limit'   => human_size($limits['pdf']),
            'contact' => Settings::string('site.contact_email'),
        ]);
    }

    /**
     * Create a paper. Expects already-validated metadata; files are handled
     * here so that uploads, hashes and OpenTimestamps proofs stay consistent.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $pdfFile   $_FILES entry
     * @param array<int,array<string,mixed>> $attachmentFiles
     * @return array{ok:bool,error?:string,paper_id?:int,paper?:array}
     */
    public static function create(array $input, ?array $pdfFile, array $attachmentFiles, ?array $actor = null): array
    {
        $actor ??= Auth::user();
        if ($actor === null) {
            return ['ok' => false, 'error' => __('auth.login_required')];
        }

        $isAdminProxy = !empty($input['proxy_upload']);
        $adminExempt = $isAdminProxy && !empty($input['size_exempt']);
        $limits = self::limits($adminExempt);

        if ($pdfFile === null) {
            return ['ok' => false, 'error' => __('upload.error_pdf_required')];
        }

        $pdf = Uploader::store($pdfFile, [
            'kind'      => Uploader::KIND_PDF,
            'max_bytes' => $limits['pdf'],
            'exempt'    => $adminExempt,
            'trusted_local' => !empty($pdfFile['trusted_local']),
        ]);
        if (!$pdf['ok']) {
            return ['ok' => false, 'error' => (string) $pdf['error']];
        }
        $pdfData = $pdf['data'];

        $storedAttachments = [];
        foreach ($attachmentFiles as $file) {
            $stored = Uploader::store($file, [
                'kind'      => Uploader::KIND_ARCHIVE,
                'max_bytes' => $limits['attachment'],
                'exempt'    => $adminExempt,
            ]);
            if (!$stored['ok']) {
                // Roll back the PDF we just wrote: submissions are atomic.
                @unlink($pdfData['absolute']);
                foreach ($storedAttachments as $done) {
                    @unlink($done['absolute']);
                }
                return ['ok' => false, 'error' => (string) $stored['error']];
            }
            $storedAttachments[] = $stored['data'];
        }

        $db = Database::instance();
        $autoApprove = Settings::bool('moderation.auto_approve') && !empty($actor['role']) && $actor['role'] === 'admin';

        try {
            $paperId = $db->transaction(function () use (
                $input, $actor, $pdfData, $storedAttachments, $adminExempt, $isAdminProxy, $autoApprove
            ): int {
                $now = Database::instance()->now();
                $uid = Paper::generateUid();
                $status = !empty($input['as_draft'])
                    ? Paper::STATUS_DRAFT
                    : ($autoApprove ? Paper::STATUS_APPROVED : Paper::STATUS_PENDING);

                $id = Paper::create([
                    'uid'               => $uid,
                    'slug'              => Str::slug((string) $input['title'], 90),
                    'title'             => $input['title'],
                    'subtitle'          => $input['subtitle'] ?? null,
                    'abstract'          => $input['abstract'],
                    'language'          => $input['language'] ?? 'en',
                    'language_custom'   => $input['language_custom'] ?? null,
                    'section_id'        => !empty($input['section_id']) ? (int) $input['section_id'] : null,
                    'category_id'       => !empty($input['category_id']) ? (int) $input['category_id'] : null,
                    // "Other / not listed": the author's own field name. Its
                    // presence blocks approval until a moderator classifies it.
                    'category_other'    => !empty($input['category_other']) ? (string) $input['category_other'] : null,
                    'keywords'          => $input['keywords'] ?? null,
                    'license'           => $input['license'] ?? null,
                    'doi'               => $input['doi'] ?? null,
                    // Archived works (bulk-imported public-domain / openly
                    // licensed papers) are marked as such: the UI shows their
                    // rights status, the original publication date and the
                    // copyright expiry date, and hides version/timestamp history.
                    'origin'               => ($input['origin'] ?? Paper::ORIGIN_SUBMISSION) === Paper::ORIGIN_ARCHIVE
                        ? Paper::ORIGIN_ARCHIVE : Paper::ORIGIN_SUBMISSION,
                    'origin_source'        => $input['origin_source'] ?? null,
                    'origin_published_at'  => $input['origin_published_at'] ?? null,
                    'copyright_expired_at' => $input['copyright_expired_at'] ?? null,
                    'uploader_id'       => (int) ($input['uploader_id'] ?? $actor['id']),
                    'proxy_uploader_id' => $isAdminProxy ? (int) $actor['id'] : null,
                    'status'            => $status,
                    'visibility'        => $input['visibility'] ?? 'public',
                    'pdf_path'          => $pdfData['path'],
                    'pdf_name'          => $pdfData['original_name'],
                    'pdf_size'          => $pdfData['size'],
                    'pdf_sha256'        => $pdfData['sha256'],
                    'size_exempt'       => $adminExempt ? 1 : 0,
                    'size_exempt_note'  => $adminExempt ? ($input['size_exempt_note'] ?? null) : null,
                    'submitted_at'      => $status === Paper::STATUS_DRAFT ? null : $now,
                    'published_at'      => $status === Paper::STATUS_APPROVED ? $now : null,
                    'created_at'        => $now,
                ]);

                foreach ($storedAttachments as $file) {
                    Attachment::create([
                        'paper_id'      => $id,
                        'kind'          => 'archive',
                        'original_name' => $file['original_name'],
                        'stored_name'   => $file['stored_name'],
                        'path'          => $file['path'],
                        'size'          => $file['size'],
                        'mime'          => $file['mime'],
                        'sha256'        => $file['sha256'],
                        'size_exempt'   => $adminExempt ? 1 : 0,
                        'uploaded_by'   => (int) $actor['id'],
                        'created_at'    => $now,
                    ]);
                }

                self::saveRelations($id, $input);

                return $id;
            });
        } catch (\Throwable $e) {
            Logger::error('paper create failed: ' . $e->getMessage());
            @unlink($pdfData['absolute']);
            foreach ($storedAttachments as $done) {
                @unlink($done['absolute']);
            }
            return ['ok' => false, 'error' => __('upload.error_storage')];
        }

        AuditLog::record(
            $isAdminProxy ? 'paper.proxy_upload' : 'paper.create',
            'paper',
            $paperId,
            ['uid' => Paper::find($paperId)['uid'] ?? '', 'exempt' => $adminExempt]
        );

        // Revision 1 of a brand new paper.
        if (Settings::bool('versions.enabled')) {
            PaperVersion::record($paperId, [
                'path'          => $pdfData['path'],
                'original_name' => $pdfData['original_name'],
                'size'          => $pdfData['size'],
                'sha256'        => $pdfData['sha256'],
            ], $input['version_note'] ?? null, (int) $actor['id'], $adminExempt);
            Paper::update($paperId, ['version_no' => 1]);
        }

        // Timestamping happens for every upload, approved or not.
        self::timestampPaper($paperId);

        $paper = Paper::find($paperId) ?? [];

        Mailer::notifyPaperEvent(
            $isAdminProxy ? Mailer::EVENT_PROXY : Mailer::EVENT_NEW_PAPER,
            $paper,
            ['version' => 'v1']
        );

        if (!$isAdminProxy) {
            Mailer::notifyPaperEvent(Mailer::EVENT_SUBMITTED, $paper, ['version' => 'v1']);
        }

        // Full-automatic AI mode reviews (and optionally publishes) at once.
        if (self::shouldAutoReview()) {
            self::runAiReview($paperId, Settings::bool('ai.auto_publish'));
        }

        return ['ok' => true, 'paper_id' => $paperId, 'paper' => Paper::find($paperId) ?? []];
    }

    /** Update metadata of an existing paper (owner or admin). */
    public static function update(int $paperId, array $input, ?array $pdfFile, array $attachmentFiles, bool $adminExempt = false): array
    {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found')];
        }
        $limits = self::limits($adminExempt);
        $updates = [
            'title'       => $input['title'],
            'slug'        => Str::slug((string) $input['title'], 90),
            'subtitle'    => $input['subtitle'] ?? null,
            'abstract'    => $input['abstract'],
            'language'    => $input['language'] ?? $paper['language'],
            // null when a catalogue language was picked, free text otherwise.
            'language_custom' => $input['language_custom'] ?? null,
            'section_id'  => !empty($input['section_id']) ? (int) $input['section_id'] : null,
            'category_id' => !empty($input['category_id']) ? (int) $input['category_id'] : null,
            'category_other' => !empty($input['category_other']) ? (string) $input['category_other'] : null,
            'keywords'    => $input['keywords'] ?? null,
            'license'     => $input['license'] ?? null,
            'doi'         => $input['doi'] ?? null,
            'visibility'  => $input['visibility'] ?? $paper['visibility'],
        ];

        if ($pdfFile !== null) {
            $stored = Uploader::store($pdfFile, [
                'kind'      => Uploader::KIND_PDF,
                'max_bytes' => $limits['pdf'],
                'exempt'    => $adminExempt,
            ]);
            if (!$stored['ok']) {
                return ['ok' => false, 'error' => (string) $stored['error']];
            }
            $old = Paper::pdfDiskPath($paper);
            // Same gate as newVersion(): an already published paper keeps
            // serving its reviewed file until the replacement is approved.
            $wasPublished = $paper['status'] === Paper::STATUS_APPROVED || !empty($paper['published_at']);
            if (!$wasPublished) {
                $updates['pdf_path'] = $stored['data']['path'];
                $updates['pdf_name'] = $stored['data']['original_name'];
                $updates['pdf_size'] = $stored['data']['size'];
                $updates['pdf_sha256'] = $stored['data']['sha256'];
            }
            if ($adminExempt) {
                $updates['size_exempt'] = 1;
                $updates['size_exempt_note'] = $input['size_exempt_note'] ?? null;
            }

            if (Settings::bool('versions.enabled')) {
                // Record the revision BEFORE the pointer moves: the old file has
                // to stay on disk because its proof is bound to those bytes.
                PaperVersion::record((int) $paper['id'], [
                    'path'          => $stored['data']['path'],
                    'original_name' => $stored['data']['original_name'],
                    'size'          => $stored['data']['size'],
                    'sha256'        => $stored['data']['sha256'],
                ], $input['version_note'] ?? null, (int) (Auth::id() ?? 0), $adminExempt);
                $updates['version_no'] = PaperVersion::nextVersionNumber((int) $paper['id']) - 1;
                if ($wasPublished) {
                    // Queued, not live.
                    Database::instance()->query(
                        'UPDATE {{paper_versions}} SET published_at = NULL'
                        . ' WHERE paper_id = :id ORDER BY version_no DESC LIMIT 1',
                        ['id' => (int) $paper['id']]
                    );
                }
            } elseif ($old !== null) {
                // Legacy behaviour when versioning is switched off.
                @unlink($old);
            }
        }

        $storedAttachments = [];
        foreach ($attachmentFiles as $file) {
            $stored = Uploader::store($file, [
                'kind'      => Uploader::KIND_ARCHIVE,
                'max_bytes' => $limits['attachment'],
                'exempt'    => $adminExempt,
            ]);
            if (!$stored['ok']) {
                foreach ($storedAttachments as $done) {
                    @unlink($done['absolute']);
                }
                return ['ok' => false, 'error' => (string) $stored['error']];
            }
            $storedAttachments[] = $stored['data'];
        }

        Paper::update($paperId, $updates);

        // An edited *published* paper goes back to the editors — the same rule
        // as for a new version. An administrator's edit is applied directly.
        if ($paper['status'] === Paper::STATUS_APPROVED && !Auth::isAdmin()) {
            Paper::update($paperId, [
                'status'        => Paper::STATUS_PENDING,
                'submitted_at'  => Database::instance()->now(),
                'reject_reason' => null,
            ]);
            AuditLog::record('paper.edit', 'paper', $paperId, ['back_to_review' => true]);
            Mailer::notifyPaperEvent(Mailer::EVENT_SUBMITTED, Paper::find($paperId) ?? $paper, [
                'note' => (string) ($input['version_note'] ?? ''),
            ]);
        }

        foreach ($storedAttachments as $file) {
            Attachment::create([
                'paper_id'      => $paperId,
                'kind'          => 'archive',
                'original_name' => $file['original_name'],
                'stored_name'   => $file['stored_name'],
                'path'          => $file['path'],
                'size'          => $file['size'],
                'mime'          => $file['mime'],
                'sha256'        => $file['sha256'],
                'size_exempt'   => $adminExempt ? 1 : 0,
                'uploaded_by'   => (int) (Auth::id() ?? 0),
                'created_at'    => Database::instance()->now(),
            ]);
        }

        self::saveRelations($paperId, $input);

        if ($pdfFile !== null) {
            self::timestampPaper($paperId);
        }

        AuditLog::record('paper.update', 'paper', $paperId);
        return ['ok' => true, 'paper_id' => $paperId];
    }

    /** @param array<string,mixed> $input */
    /**
     * Turn what the submitter typed into a user id (accepts a uid or a numeric
     * id; anything unknown is ignored rather than failing the submission).
     */
    private static function authorUserId(mixed $value): ?int
    {
        static $cache = [];
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (array_key_exists($value, $cache)) {
            return $cache[$value];
        }
        $user = ctype_digit($value) ? User::find((int) $value) : User::findByUid($value);
        return $cache[$value] = ($user !== null ? (int) $user['id'] : null);
    }

    private static function saveRelations(int $paperId, array $input): void
    {
        $authors = [];
        $names = $input['author_name'] ?? [];
        if (is_array($names)) {
            foreach ($names as $index => $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $authors[] = [
                    'name'             => $name,
                    'affiliation'      => $input['author_affiliation'][$index] ?? null,
                    'email'            => $input['author_email'][$index] ?? null,
                    'orcid'            => $input['author_orcid'][$index] ?? null,
                    // Optional link to a site account, typed as a uid by the submitter.
                    'user_id'          => self::authorUserId($input['author_user'][$index] ?? null),
                    'is_corresponding' => !empty($input['author_corresponding'][$index]),
                ];
            }
        }
        if ($authors !== []) {
            PaperAuthor::sync($paperId, $authors);
        }

        $links = [];
        $labels = $input['link_label'] ?? [];
        $urls = $input['link_url'] ?? [];
        $kinds = $input['link_kind'] ?? [];
        if (is_array($urls)) {
            foreach ($urls as $index => $url) {
                $url = trim((string) $url);
                if ($url === '') {
                    continue;
                }
                $links[] = [
                    'label' => $labels[$index] ?? '',
                    'url'   => $url,
                    'kind'  => $kinds[$index] ?? 'other',
                ];
            }
        }
        PaperLink::sync($paperId, $links);
    }

    /** Submit a draft (or resubmit after a rejection) for review. */
    public static function submit(int $paperId): array
    {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found')];
        }
        if ($paper['pdf_path'] === null || $paper['pdf_path'] === '') {
            return ['ok' => false, 'error' => __('upload.error_pdf_required')];
        }
        $status = Settings::bool('moderation.auto_approve') && Auth::isAdmin()
            ? Paper::STATUS_APPROVED
            : Paper::STATUS_PENDING;

        Paper::update($paperId, [
            'status'         => $status,
            'submitted_at'   => Database::instance()->now(),
            'reject_reason'  => null,
            'published_at'   => $status === Paper::STATUS_APPROVED ? Database::instance()->now() : null,
        ]);
        AuditLog::record('paper.submit', 'paper', $paperId);
        Mailer::notifyPaperEvent(Mailer::EVENT_SUBMITTED, Paper::find($paperId) ?? $paper);
        return ['ok' => true];
    }

    /** Owner withdrawal ("撤回"). Timestamp proofs are kept. */
    public static function withdraw(int $paperId, ?string $reason = null): array
    {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found')];
        }
        Paper::update($paperId, [
            'status'       => Paper::STATUS_WITHDRAWN,
            'withdrawn_at' => Database::instance()->now(),
            'review_note'  => $reason,
        ]);
        AuditLog::record('paper.withdraw', 'paper', $paperId, ['reason' => $reason]);
        Mailer::notifyPaperEvent(Mailer::EVENT_WITHDRAWN, Paper::find($paperId) ?? $paper, [
            'reason' => (string) ($reason ?? ''),
        ]);
        return ['ok' => true];
    }

    public static function approve(int $paperId, ?string $note = null, ?string $sectionId = null): array
    {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found')];
        }

        // A paper whose author picked "Other / not listed" carries a free-text
        // field name. It must be classified by a human (or the AI) first: the
        // archive would otherwise publish papers under a non-existent area.
        if (!self::isClassified($paper)) {
            return ['ok' => false, 'error' => __('category.approve_blocked')];
        }

        $updates = [
            'status'       => Paper::STATUS_APPROVED,
            'published_at' => Database::instance()->now(),
            'reviewed_by'  => Auth::id(),
            'reviewed_at'  => Database::instance()->now(),
            'review_note'  => $note,
            'reject_reason' => null,
        ];
        if ($sectionId !== null && $sectionId !== '') {
            $updates['section_id'] = (int) $sectionId;
        } else {
            if (empty($paper['section_id'])) {
                $default = Section::defaultSection();
                if ($default !== null) {
                    $updates['section_id'] = (int) $default['id'];
                }
            }
        }

        // A revision that was waiting becomes the current file only now, so a
        // file can never be served before an editor has seen it.
        $queued = Database::instance()->select(
            'SELECT * FROM {{paper_versions}} WHERE paper_id = :id AND published_at IS NULL'
            . ' ORDER BY version_no DESC LIMIT 1',
            ['id' => $paperId]
        )[0] ?? null;
        if ($queued !== null) {
            $updates['pdf_path']   = (string) $queued['pdf_path'];
            $updates['pdf_name']   = (string) ($queued['pdf_name'] ?? 'paper.pdf');
            $updates['pdf_size']   = (int) $queued['pdf_size'];
            $updates['pdf_sha256'] = (string) ($queued['pdf_sha256'] ?? '');
            $updates['version_no'] = (int) $queued['version_no'];
        }
        Paper::update($paperId, $updates);
        if ($queued !== null) {
            Database::instance()->query(
                'UPDATE {{paper_versions}} SET published_at = :now WHERE id = :id',
                ['now' => Database::instance()->now(), 'id' => (int) $queued['id']]
            );
            // The promoted file is different bytes: it gets its own proof.
            self::timestampPaper($paperId);
        }
        AuditLog::record('paper.approve', 'paper', $paperId, ['note' => $note]);
        Mailer::notifyPaperEvent(Mailer::EVENT_APPROVED, Paper::find($paperId) ?? [], [
            'note' => (string) ($note ?? ''),
        ]);
        return ['ok' => true];
    }

    /**
     * A paper is "classified" when it sits in a real subject area. Papers whose
     * author chose "Other / not listed" carry only a free-text field name and
     * must be classified before they may be published.
     */
    public static function isClassified(array $paper): bool
    {
        if ((int) ($paper['category_id'] ?? 0) > 0) {
            return true;
        }
        return trim((string) ($paper['category_other'] ?? '')) === '';
    }

    /**
     * Give a paper a real subject area, creating it when asked.
     *
     * Used by the moderator console and by the AI reviewer; both are allowed to
     * create areas, authors are not.
     *
     * @return array{ok:bool,error?:string,category_id?:int,created?:bool}
     */
    public static function reclassify(
        int $paperId,
        ?int $categoryId,
        ?string $newAreaName = null,
        ?string $parentSlug = null,
        string $source = 'admin'
    ): array {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found')];
        }

        $created = false;
        if (($categoryId === null || $categoryId <= 0) && $newAreaName !== null && trim($newAreaName) !== '') {
            $categoryId = self::createArea(trim($newAreaName), $parentSlug);
            $created = $categoryId !== null;
        }
        if ($categoryId === null || $categoryId <= 0 || Category::find($categoryId) === null) {
            return ['ok' => false, 'error' => __('admin.name_required')];
        }

        Paper::update($paperId, [
            'category_id'    => (int) $categoryId,
            'category_other' => null,
        ]);
        AuditLog::record('paper.reclassify', 'paper', $paperId, [
            'category_id' => (int) $categoryId,
            'created'     => $created,
            'source'      => $source,
            'was'         => (string) ($paper['category_other'] ?? ''),
        ]);

        return ['ok' => true, 'category_id' => (int) $categoryId, 'created' => $created];
    }

    /**
     * Create a subject area from a name (used by the AI and the console).
     * Returns the new id, or null when the name yields no usable slug.
     */
    public static function createArea(string $name, ?string $parentSlug = null): ?int
    {
        $slug = Str::slug($name, 60);
        if ($slug === '') {
            return null;
        }
        $existing = Category::findBySlug($slug);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $parentId = null;
        if ($parentSlug !== null && trim($parentSlug) !== '') {
            $parent = Category::findBySlug(trim($parentSlug));
            $parentId = $parent !== null ? (int) $parent['id'] : null;
        }

        $names = ['en' => $name];
        if (preg_match('/[\x{4e00}-\x{9fff}]/u', $name) === 1) {
            $names = ['zh-CN' => $name];
        }

        $db = Database::instance();
        $id = Category::create([
            'slug'       => $slug,
            'names'      => json_encode($names, JSON_UNESCAPED_UNICODE),
            'parent_id'  => $parentId,
            'sort_order' => 999,
            'created_at' => $db->now(),
        ]);
        AuditLog::record('category.create', 'category', $id, ['slug' => $slug, 'by' => 'auto']);

        return $id > 0 ? $id : null;
    }

    public static function reject(int $paperId, string $reason, ?string $note = null): array    {
        Paper::update($paperId, [
            'status'        => Paper::STATUS_REJECTED,
            'reject_reason' => $reason,
            'review_note'   => $note,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => Database::instance()->now(),
        ]);
        AuditLog::record('paper.reject', 'paper', $paperId, ['reason' => $reason]);
        Mailer::notifyPaperEvent(Mailer::EVENT_REJECTED, Paper::find($paperId) ?? [], [
            'reason' => $reason,
            'note'   => (string) ($note ?? ''),
        ]);
        return ['ok' => true];
    }

    public static function takedown(int $paperId, string $reason): array
    {
        Paper::update($paperId, [
            'status'          => Paper::STATUS_TAKEDOWN,
            'takedown_reason' => $reason,
            'reviewed_by'     => Auth::id(),
            'reviewed_at'     => Database::instance()->now(),
        ]);
        AuditLog::record('paper.takedown', 'paper', $paperId, ['reason' => $reason]);
        Mailer::notifyPaperEvent(Mailer::EVENT_TAKEDOWN, Paper::find($paperId) ?? [], [
            'reason' => $reason,
        ]);
        return ['ok' => true];
    }

    public static function assignSection(int $paperId, ?int $sectionId): array
    {
        Paper::update($paperId, ['section_id' => $sectionId]);
        AuditLog::record('paper.section', 'paper', $paperId, ['section_id' => $sectionId]);
        return ['ok' => true];
    }

    public static function toggleFeatured(int $paperId, bool $featured): array
    {
        Paper::update($paperId, ['is_featured' => $featured ? 1 : 0]);
        AuditLog::record('paper.feature', 'paper', $paperId, ['featured' => $featured]);
        return ['ok' => true];
    }

    /**
     * OpenTimestamps proof for the current PDF (and any new attachments).
     * Never blocks a submission: calendar outages are logged and retried by
     * the `bin/ots-upgrade.php` cron job.
     */
    public static function timestampPaper(int $paperId): array
    {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false];
        }
        if (!Settings::bool('ots.enabled')) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $pdfPath = Paper::pdfDiskPath($paper);
        if ($pdfPath === null) {
            Logger::warning('cannot timestamp paper: PDF missing on disk', [
                'paper_id' => $paperId,
                'pdf_path' => $paper['pdf_path'],
            ]);
            return ['ok' => false, 'error' => 'missing file'];
        }

        $results = [];
        $results['pdf'] = OpenTimestamps::stamp(
            $paperId,
            $pdfPath,
            (string) ($paper['pdf_name'] ?: 'paper.pdf'),
            'pdf',
            null
        );

        foreach (Paper::attachments($paperId) as $attachment) {
            $existing = \Athenaeum\Models\Timestamp::findForFile(
                (string) $attachment['sha256'],
                $paperId,
                'attachment'
            );
            if ($existing !== null) {
                continue;
            }
            $path = Attachment::diskPath($attachment);
            if ($path === null) {
                continue;
            }
            $results['attachment_' . $attachment['id']] = OpenTimestamps::stamp(
                $paperId,
                $path,
                (string) $attachment['original_name'],
                'attachment',
                (int) $attachment['id']
            );
        }

        return $results;
    }

    /** Papers the public may see for a given section slug. */
    public static function publicListing(array $filters, int $page, int $perPage): array
    {
        return Paper::search($filters, $page, $perPage);
    }

    public static function audit(string $action, string $type, int|string $id, array $meta = []): void
    {
        AuditLog::record($action, $type, $id, $meta);
    }

    /**
     * Turn the upload form's language choice into a stored (code, custom) pair.
     * "Other" + free text becomes `x-<slug>` plus the author's spelling, which
     * is what makes a brand-new language show up in the archive filter.
     *
     * @return array{code:string,custom:?string}
     */
    public static function normaliseLanguage(string $code, string $custom): array
    {
        $code = trim($code);
        $custom = trim($custom);
        if ($code === \Athenaeum\Core\Languages::OTHER) {
            return [
                'code'   => \Athenaeum\Core\Languages::customCode($custom),
                'custom' => $custom !== '' ? $custom : null,
            ];
        }
        if ($code === '') {
            return ['code' => 'en', 'custom' => null];
        }
        return ['code' => $code, 'custom' => $custom !== '' ? $custom : null];
    }

    // =====================================================================
    // Version management
    // =====================================================================

    /**
     * Publish a new revision of an existing paper.
     *
     * The previous PDF is *not* discarded: it stays on disk, keeps its version
     * row and keeps the OpenTimestamps proof that was made for it, so the
     * evidence for "this text existed on that date" survives the update. The
     * new file gets its own proof immediately.
     *
     * @param array<string,mixed>|null $pdfFile $_FILES entry
     * @return array{ok:bool,error?:string,version?:int,paper?:array}
     */
    public static function newVersion(
        int $paperId,
        ?array $pdfFile,
        ?string $note = null,
        bool $adminExempt = false,
        ?array $actor = null
    ): array {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found')];
        }
        if ($pdfFile === null) {
            return ['ok' => false, 'error' => __('upload.error_pdf_required')];
        }
        if (!Settings::bool('versions.enabled')) {
            return ['ok' => false, 'error' => __('version.disabled')];
        }
        $actor ??= Auth::user();

        $limits = self::limits($adminExempt);
        $stored = Uploader::store($pdfFile, [
            'kind'      => Uploader::KIND_PDF,
            'max_bytes' => $limits['pdf'],
            'exempt'    => $adminExempt,
        ]);
        if (!$stored['ok']) {
            return ['ok' => false, 'error' => (string) $stored['error']];
        }
        $file = $stored['data'];

        // Was this paper ever published? If so the new file must wait for
        // approval before readers can reach it.
        $wasPublished = $paper['status'] === Paper::STATUS_APPROVED || !empty($paper['published_at']);
        $versionNo = PaperVersion::nextVersionNumber($paperId);
        PaperVersion::create([
            'paper_id'    => $paperId,
            'version_no'  => $versionNo,
            'label'       => 'v' . $versionNo,
            'note'        => $note,
            'pdf_path'    => $file['path'],
            'pdf_name'    => $file['original_name'],
            'pdf_size'    => $file['size'],
            'pdf_sha256'  => $file['sha256'],
            'uploaded_by' => $actor['id'] ?? null,
            'size_exempt' => $adminExempt ? 1 : 0,
            'created_at'  => Database::instance()->now(),
            'published_at' => $wasPublished ? null : Database::instance()->now(),
        ]);

        $updates = [
            'version_no' => $versionNo,
            // A new revision of a published paper goes back to the editors:
            // readers must never silently get a different text than the one
            // that was reviewed.
            'status'     => $paper['status'] === Paper::STATUS_APPROVED
                ? Paper::STATUS_PENDING
                : $paper['status'],
        ];
        if (!$wasPublished) {
            $updates['pdf_path']   = $file['path'];
            $updates['pdf_name']   = $file['original_name'];
            $updates['pdf_size']   = $file['size'];
            $updates['pdf_sha256'] = $file['sha256'];
        }
        if ($adminExempt) {
            $updates['size_exempt'] = 1;
        }
        Paper::update($paperId, $updates);

        AuditLog::record('paper.version', 'paper', $paperId, [
            'version' => $versionNo,
            'sha256'  => $file['sha256'],
            'note'    => $note,
        ]);

        // New file → new proof. The old proof stays attached to the old file.
        self::timestampPaper($paperId);
        Mailer::notifyPaperEvent(Mailer::EVENT_SUBMITTED, Paper::find($paperId) ?? $paper, [
            'version' => 'v' . $versionNo,
        ]);

        if (self::shouldAutoReview()) {
            self::runAiReview($paperId, Settings::bool('ai.auto_publish'));
        }

        return [
            'ok'      => true,
            'version' => $versionNo,
            'paper'   => Paper::find($paperId) ?? [],
        ];
    }

    /** @return array<int,array<string,mixed>> revisions with their proofs */
    public static function versionsWithProofs(int $paperId): array
    {
        $out = [];
        foreach (PaperVersion::forPaper($paperId) as $version) {
            $version['timestamp'] = PaperVersion::timestampFor($version);
            $version['uploader'] = PaperVersion::uploader($version);
            $out[] = $version;
        }
        return $out;
    }

    // =====================================================================
    // AI review
    // =====================================================================

    public static function shouldAutoReview(): bool
    {
        return AiReviewer::mode() === AiReviewer::MODE_AUTO;
    }

    /** Mark every given paper as waiting for an AI pass. */
    public static function queueAiReview(array $paperIds): int
    {
        $queued = 0;
        foreach ($paperIds as $paperId) {
            $paper = Paper::find((int) $paperId);
            if ($paper === null) {
                continue;
            }
            Paper::update((int) $paper['id'], [
                'ai_status' => 'queued',
                'ai_reason' => null,
                'ai_decision' => null,
                'ai_confidence' => null,
            ]);
            $queued++;
        }
        return $queued;
    }

    /**
     * Run the reviewer on one paper, store the verdict, and — depending on the
     * mode — publish, refuse or leave it in the human queue.
     *
     * @return array{ok:bool,error?:string,verdict?:array,action:string,paper?:array}
     */
    public static function runAiReview(int $paperId, bool $autoPublish = false): array
    {
        $paper = Paper::find($paperId);
        if ($paper === null) {
            return ['ok' => false, 'error' => __('paper.not_found'), 'action' => 'none'];
        }
        if (!AiReviewer::enabled()) {
            return ['ok' => false, 'error' => __('ai.not_configured'), 'action' => 'none'];
        }

        Paper::update($paperId, ['ai_status' => 'running']);
        $pdfPath = Paper::pdfDiskPath($paper);
        $verdict = AiReviewer::review($paper, $pdfPath);

        if (!$verdict['ok']) {
            Paper::update($paperId, [
                'ai_status'   => 'failed',
                'ai_reason'   => (string) ($verdict['error'] ?? 'error'),
                'ai_reviewed_at' => Database::instance()->now(),
            ]);
            AuditLog::record('ai.review', 'paper', $paperId, ['ok' => false, 'error' => $verdict['error']]);
            return ['ok' => false, 'error' => (string) $verdict['error'], 'action' => 'failed'];
        }

        // Resolve the subject area first: the AI may assign an existing area or
        // propose a brand new one, and assigning it also clears the author's
        // "Other / not listed" note (which is what unblocks approval).
        $assignedCategoryId = null;
        if (Settings::bool('ai.assign_category')) {
            if (!empty($verdict['category_slug'])) {
                $category = \Athenaeum\Models\Category::findBySlug((string) $verdict['category_slug']);
                if ($category !== null) {
                    $assignedCategoryId = (int) $category['id'];
                }
            }
            if ($assignedCategoryId === null
                && Settings::bool('ai.create_categories')
                && is_array($verdict['category_new'] ?? null)
                && trim((string) ($verdict['category_new']['name'] ?? '')) !== ''
            ) {
                $assignedCategoryId = self::createArea(
                    (string) $verdict['category_new']['name'],
                    isset($verdict['category_new']['parent_slug'])
                        ? (string) $verdict['category_new']['parent_slug']
                        : null
                );
            }
            if ($assignedCategoryId !== null) {
                Paper::update($paperId, [
                    'category_id'    => $assignedCategoryId,
                    'category_other' => null,
                ]);
            }
        }

        Paper::update($paperId, [
            'ai_status'     => 'done',
            'ai_decision'   => $verdict['decision'],
            'ai_confidence' => $verdict['confidence'],
            'ai_reason'     => $verdict['reason'],
            'ai_model'      => $verdict['model'],
            'ai_reviewed_at' => Database::instance()->now(),
            'ai_payload'    => json_encode([
                'tags'          => $verdict['tags'],
                'category_slug' => $verdict['category_slug'],
                'category_new'  => $verdict['category_new'] ?? null,
                'category_id'   => $assignedCategoryId,
                'section_slug'  => $verdict['section_slug'],
                'pdf_text'      => $verdict['pdf_text'],
                'input_chars'   => $verdict['input_chars'],
                'prompt_tokens' => $verdict['prompt_tokens'] ?? null,
                'completion_tokens' => $verdict['completion_tokens'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $action = 'recommended';
        $minConfidence = Settings::int('ai.min_confidence', 80);
        $confident = $verdict['confidence'] >= $minConfidence;

        $sectionId = null;
        if (Settings::bool('ai.assign_section') && !empty($verdict['section_slug'])) {
            $section = Section::findBySlug((string) $verdict['section_slug']);
            if ($section !== null) {
                $sectionId = (int) $section['id'];
            }
        }

        if ($autoPublish && $confident) {
            if ($verdict['decision'] === AiReviewer::DECISION_APPROVE) {
                self::approve($paperId, __('ai.approved_note', [
                    'confidence' => (string) $verdict['confidence'],
                ]), $sectionId !== null ? (string) $sectionId : null);
                $action = 'published';
            } elseif ($verdict['decision'] === AiReviewer::DECISION_REJECT) {
                self::reject($paperId, $verdict['reason'] !== '' ? $verdict['reason'] : __('ai.rejected_note'), $verdict['reason']);
                $action = 'rejected';
            }
        } elseif ($sectionId !== null && $paper['status'] === Paper::STATUS_PENDING) {
            // Remember the suggestion without publishing anything.
            Paper::update($paperId, ['section_id' => $sectionId]);
        }

        AuditLog::record('ai.review', 'paper', $paperId, [
            'decision'   => $verdict['decision'],
            'confidence' => $verdict['confidence'],
            'action'     => $action,
            'model'      => $verdict['model'],
        ]);

        return [
            'ok'      => true,
            'verdict' => $verdict,
            'action'  => $action,
            'paper'   => Paper::find($paperId) ?? [],
        ];
    }

    /**
     * Batch pass for the "AI 一键审核" button (semi-automatic mode).
     *
     * @param int[] $paperIds
     * @return array{checked:int,published:int,rejected:int,recommended:int,failed:int,duration:float,details:array<int,array<string,mixed>>}
     */
    public static function runAiBatch(array $paperIds, bool $autoPublish = false): array
    {
        $stats = [
            'checked' => 0, 'published' => 0, 'rejected' => 0,
            'recommended' => 0, 'failed' => 0, 'duration' => 0.0, 'details' => [],
        ];
        $started = microtime(true);

        foreach ($paperIds as $paperId) {
            $result = self::runAiReview((int) $paperId, $autoPublish);
            $stats['checked']++;
            $stats['details'][] = [
                'paper_id' => (int) $paperId,
                'ok'       => $result['ok'],
                'action'   => $result['action'],
                'decision' => $result['verdict']['decision'] ?? null,
                'confidence' => $result['verdict']['confidence'] ?? null,
                'error'    => $result['error'] ?? null,
            ];
            match ($result['action']) {
                'published'   => $stats['published']++,
                'rejected'    => $stats['rejected']++,
                'recommended' => $stats['recommended']++,
                default       => $stats['failed']++,
            };
        }

        $stats['duration'] = round(microtime(true) - $started, 1);
        AuditLog::record('ai.batch', 'paper', null, [
            'checked' => $stats['checked'],
            'published' => $stats['published'],
            'rejected' => $stats['rejected'],
            'failed' => $stats['failed'],
        ]);
        return $stats;
    }
}
