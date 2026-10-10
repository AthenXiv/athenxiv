<?php

declare(strict_types=1);

namespace Athenaeum\Services;

use Athenaeum\Core\Config;
use Athenaeum\Core\I18n;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Settings;
use Athenaeum\Models\Paper;

/**
 * Outgoing mail: a small SMTP client (no PHPMailer, no Composer) plus the
 * notification rules for paper events.
 *
 * Works with any provider that speaks SMTP with AUTH LOGIN/PLAIN — including
 * QQ Mail (smtp.qq.com:465, "authorisation code" instead of the account
 * password), 163, Gmail, Outlook, Yandex, Fastmail and self-hosted servers.
 * `mail.transport = mail` falls back to PHP's mail() for hosts without SMTP.
 */
final class Mailer
{
    public const EVENT_NEW_PAPER   = 'new_paper';
    public const EVENT_SUBMITTED   = 'paper_submitted';
    public const EVENT_APPROVED    = 'paper_approved';
    public const EVENT_REJECTED    = 'paper_rejected';
    public const EVENT_WITHDRAWN   = 'paper_withdrawn';
    public const EVENT_TAKEDOWN    = 'paper_takedown';
    public const EVENT_PROXY       = 'paper_proxy_upload';
    public const EVENT_WELCOME     = 'welcome';
    public const EVENT_TEST        = 'test';
    /** Six-digit code for a forgotten password. */
    public const EVENT_RESET_CODE  = 'reset_code';

    /** @return array{ok:bool,reason:string} */
    public static function configured(): array
    {
        if (!Settings::bool('mail.enabled')) {
            return ['ok' => false, 'reason' => 'disabled'];
        }
        if (Settings::string('mail.transport') === 'mail') {
            return ['ok' => true, 'reason' => 'php mail()'];
        }
        if (Settings::string('mail.host') === '') {
            return ['ok' => false, 'reason' => 'no SMTP host'];
        }
        if (Settings::string('mail.username') === '' || Settings::string('mail.password') === '') {
            return ['ok' => false, 'reason' => 'SMTP username or authorisation code missing'];
        }
        if (self::fromAddress() === '') {
            return ['ok' => false, 'reason' => 'no sender address'];
        }
        return ['ok' => true, 'reason' => 'smtp'];
    }

    public static function fromAddress(): string
    {
        $from = Settings::string('mail.from_address');
        if ($from !== '') {
            return $from;
        }
        // Most providers require the authenticated account as the envelope
        // sender, so fall back to the username when it looks like an address.
        $username = Settings::string('mail.username');
        return filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : '';
    }

    /**
     * Send one message.
     *
     * @return array{ok:bool,error:?string,log:string[]}
     */
    public static function send(
        string $to,
        string $subject,
        string $html,
        string $text = '',
        ?string $replyTo = null
    ): array {
        $status = self::configured();
        if (!$status['ok']) {
            return ['ok' => false, 'error' => 'mail not configured: ' . $status['reason'], 'log' => []];
        }
        // Header-injection guard first: a CRLF smuggled into an address must be
        // reported as an injection attempt, not merely as an invalid address.
        if (preg_match('/[\r\n]/', $subject) || preg_match('/[\r\n]/', $to)) {
            return ['ok' => false, 'error' => 'header injection attempt blocked', 'log' => []];
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid recipient', 'log' => []];
        }

        $from = self::fromAddress();
        $fromName = Settings::string('mail.from_name') ?: Settings::siteName();
        $text = $text !== '' ? $text : self::htmlToText($html);

        if (Settings::string('mail.transport') === 'mail') {
            $headers = self::headers($from, $fromName, $replyTo);
            $ok = @mail($to, self::encodeHeader($subject), $text, implode("\r\n", $headers));
            return ['ok' => $ok, 'error' => $ok ? null : 'mail() returned false', 'log' => []];
        }

        return self::smtpSend($to, $subject, $html, $text, $from, $fromName, $replyTo);
    }

    /** @return array{ok:bool,error:?string,log:string[]} */
    private static function smtpSend(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $from,
        string $fromName,
        ?string $replyTo
    ): array {
        $host = Settings::string('mail.host');
        $port = max(1, Settings::int('mail.port', 465));
        $encryption = strtolower(Settings::string('mail.encryption', 'ssl'));
        $username = Settings::string('mail.username');
        $password = Settings::string('mail.password');
        $timeout = max(5, (int) Config::get('http.timeout', 20));
        $log = [];

        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => array_filter([
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'cafile'            => \Athenaeum\Core\Http::caBundle() ?? '',
        ])]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            $host = (string) Settings::get('mail.host');
            $port = (int) Settings::get('mail.port', 465);
            // A bare "connection refused" is the most common misconfiguration
            // (a stale host, a blocked port, or a local test address), so name
            // the endpoint and say what usually causes it.
            $hint = __('mail.connect_failed', [
                'host'  => $host,
                'port'  => (string) $port,
                'error' => trim($errstr) !== '' ? $errstr : ('errno ' . $errno),
            ]);
            Logger::warning('mail connection failed', [
                'target' => $target, 'errno' => $errno, 'errstr' => $errstr,
            ]);
            return ['ok' => false, 'error' => $hint, 'log' => $log];
        }
        stream_set_timeout($socket, $timeout);

        $read = static function () use ($socket, &$log): string {
            $data = '';
            while (($line = fgets($socket, 1024)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            $log[] = '< ' . trim($data);
            return $data;
        };
        $write = static function (string $command) use ($socket, &$log): void {
            $log[] = '> ' . preg_replace('/^AUTH LOGIN.*/i', 'AUTH LOGIN —', $command);
            fwrite($socket, $command . "\r\n");
        };
        $expect = static function (string $response, string $code) use (&$log): bool {
            return str_starts_with(trim($response), $code);
        };

        $greeting = $read();
        if (!$expect($greeting, '220')) {
            fclose($socket);
            return ['ok' => false, 'error' => 'unexpected greeting: ' . trim($greeting), 'log' => $log];
        }

        $hostname = parse_url(Config::baseUrl(), PHP_URL_HOST) ?: 'localhost';
        $write('EHLO ' . $hostname);
        $ehlo = $read();
        if (!$expect($ehlo, '250')) {
            $write('HELO ' . $hostname);
            $ehlo = $read();
            if (!$expect($ehlo, '250')) {
                fclose($socket);
                return ['ok' => false, 'error' => 'EHLO refused: ' . trim($ehlo), 'log' => $log];
            }
        }

        if ($encryption === 'tls') {
            $write('STARTTLS');
            $starttls = $read();
            if (!$expect($starttls, '220')) {
                fclose($socket);
                return ['ok' => false, 'error' => 'STARTTLS refused: ' . trim($starttls), 'log' => $log];
            }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['ok' => false, 'error' => 'TLS handshake failed', 'log' => $log];
            }
            $write('EHLO ' . $hostname);
            $read();
        }

        // ---- authentication -------------------------------------------------
        if ($username !== '') {
            $authPlain = base64_encode("\0" . $username . "\0" . $password);
            $write('AUTH PLAIN ' . $authPlain);
            $auth = $read();
            if (!$expect($auth, '235')) {
                // Fall back to AUTH LOGIN, which some servers prefer.
                $write('AUTH LOGIN');
                $login = $read();
                if (!$expect($login, '334')) {
                    fclose($socket);
                    return ['ok' => false, 'error' => 'authentication refused: ' . trim($auth), 'log' => $log];
                }
                $write(base64_encode($username));
                $read();
                $write(base64_encode($password));
                $result = $read();
                if (!$expect($result, '235')) {
                    fclose($socket);
                    return [
                        'ok'    => false,
                        'error' => 'authentication failed: ' . trim($result)
                            . ' — for QQ Mail the password must be the 16-character authorisation code, and the username the full address',
                        'log'   => $log,
                    ];
                }
            }
        }

        // ---- envelope -------------------------------------------------------
        $write('MAIL FROM:<' . $from . '>');
        $fromResponse = $read();
        if (!$expect($fromResponse, '250')) {
            fclose($socket);
            return ['ok' => false, 'error' => 'sender rejected: ' . trim($fromResponse), 'log' => $log];
        }
        $write('RCPT TO:<' . $to . '>');
        $rcpt = $read();
        if (!$expect($rcpt, '250') && !$expect($rcpt, '251')) {
            fclose($socket);
            return ['ok' => false, 'error' => 'recipient rejected: ' . trim($rcpt), 'log' => $log];
        }
        $write('DATA');
        $data = $read();
        if (!$expect($data, '354')) {
            fclose($socket);
            return ['ok' => false, 'error' => 'DATA refused: ' . trim($data), 'log' => $log];
        }

        $headers = self::headers($from, $fromName, $replyTo, $to, $subject);
        $boundary = 'athenxiv-' . bin2hex(random_bytes(8));
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text), 76, "\r\n")
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html), 76, "\r\n")
            . "--{$boundary}--\r\n";

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        // Dot-stuffing, per RFC 5321.
        $message = preg_replace('/^\./m', '..', $message) ?? $message;
        fwrite($socket, $message . "\r\n.\r\n");
        $sent = $read();

        $write('QUIT');
        @fclose($socket);

        if (!$expect($sent, '250')) {
            return ['ok' => false, 'error' => 'message rejected: ' . trim($sent), 'log' => $log];
        }
        return ['ok' => true, 'error' => null, 'log' => $log];
    }

    /** @return string[] */
    private static function headers(
        string $from,
        string $fromName,
        ?string $replyTo,
        string $to = '',
        string $subject = ''
    ): array {
        $headers = [];
        if ($to !== '') {
            $headers[] = 'To: <' . $to . '>';
        }
        if ($subject !== '') {
            $headers[] = 'Subject: ' . self::encodeHeader($subject);
        }
        $headers[] = 'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>';
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        } else {
            $configuredReply = Settings::string('mail.reply_to');
            if ($configuredReply !== '' && filter_var($configuredReply, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: <' . $configuredReply . '>';
            }
        }
        $headers[] = 'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@'
            . (parse_url(Config::baseUrl(), PHP_URL_HOST) ?: 'localhost') . '>';
        $headers[] = 'X-Mailer: AthenXiv';
        return $headers;
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    public static function htmlToText(string $html): string
    {
        $text = preg_replace('#<(br|/p|/div|/h[1-6]|/li)\s*/?>#i', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    // =====================================================================
    // Templates & notifications
    // =====================================================================

    /**
     * Render one of the `email.*` templates from the language files.
     *
     * @param array<string,string> $vars
     * @return array{subject:string,html:string,text:string}
     */
    public static function template(string $event, array $vars, ?string $locale = null): array
    {
        $previous = I18n::locale();
        if ($locale !== null && $locale !== '') {
            I18n::setLocale($locale);
        }

        $subject = (string) __('email.subject_' . $event, $vars);
        $bodyText = (string) __('email.body_' . $event, $vars);

        if ($locale !== null && $locale !== '') {
            I18n::setLocale($previous);
        }

        $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $bodyText) ?: []));
        $html = '<div style="font-family:system-ui,Segoe UI,Helvetica,Arial,sans-serif;'
            . 'font-size:15px;line-height:1.6;color:#1c1c1a;max-width:640px">';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>' . nl2br(e($paragraph)) . '</p>';
        }
        $html .= '</div>';

        return ['subject' => $subject, 'html' => $html, 'text' => $bodyText];
    }

    /** @param array<string,string> $vars */
    public static function sendTemplate(string $to, string $event, array $vars, ?string $locale = null): array
    {
        $template = self::template($event, $vars, $locale);
        $result = self::send($to, $template['subject'], $template['html'], $template['text']);
        if (!$result['ok']) {
            Logger::warning('mail not delivered', ['to' => $to, 'event' => $event, 'error' => $result['error']]);
        }
        return $result;
    }

    /**
     * The interface locale an event's mail should be written in.
     *
     * The author is written to in the language of the paper they submitted — a
     * rejection notice in a language they did not write in reads as a form
     * letter. Paper languages are more numerous than interface locales (74 vs
     * 30), so a language we do not ship falls back to the uploader's own
     * preference and then to the site default.
     *
     * @param array<string,mixed> $paper
     */
    public static function localeForPaper(array $paper, ?array $uploader = null): string
    {
        $default = (string) Settings::get('ui.default_locale', 'en');
        $paperLocale = I18n::bestMatch((string) ($paper['language'] ?? ''));
        if ($paperLocale !== null) {
            return $paperLocale;
        }
        // A custom language (x-…) carries its human name; try that too, so
        // "Deutsch" typed by hand still produces a German notification.
        $custom = (string) ($paper['language_custom'] ?? '');
        if ($custom !== '') {
            $customLocale = I18n::bestMatch($custom);
            if ($customLocale !== null) {
                return $customLocale;
            }
        }
        $uploaderLocale = I18n::bestMatch((string) ($uploader['locale'] ?? ''));
        return $uploaderLocale ?? $default;
    }

    /**
     * Fire the notifications for a paper event: the author (if enabled and the
     * paper has an uploader) and the moderation address (if enabled).
     */
    public static function notifyPaperEvent(string $event, array $paper, array $extra = []): void
    {
        if (!Settings::bool('mail.enabled')) {
            return;
        }

        $uploader = Paper::uploader($paper);
        $base = array_merge([
            'site'    => Settings::siteName(),
            'title'   => (string) ($paper['title'] ?? ''),
            'uid'     => (string) ($paper['uid'] ?? ''),
            'url'     => (string) ($paper['uid'] ?? '') !== ''
                ? Paper::publicUrl($paper)
                : Config::baseUrl(),
            'admin_url' => \Athenaeum\Core\Router::url('admin.paper', ['id' => (int) ($paper['id'] ?? 0)]),
            'contact' => Settings::string('site.contact_email'),
            'name'    => $uploader !== null ? (string) ($uploader['display_name'] ?: $uploader['nickname']) : '',
        ], array_map(static fn ($value): string => (string) $value, $extra));

        // `status` is a translated label, so it has to be built once per
        // recipient language rather than once per event.
        $varsFor = static fn (string $locale): array => $base + [
            'status' => self::statusLabelIn($locale, (string) ($paper['status'] ?? '')),
        ];

        if (Settings::bool('mail.notify_author') && $uploader !== null) {
            $to = (string) $uploader['email'];
            // The author should not be told "your paper was uploaded by an admin"
            // for proxy uploads unless the administrator is the actor.
            if ($to !== '' && !($event === self::EVENT_PROXY && (int) ($paper['uploader_id'] ?? 0) === 0)) {
                $authorLocale = self::localeForPaper($paper, $uploader);
                self::sendTemplate($to, $event, $varsFor($authorLocale), $authorLocale);
            }
        }

        if (Settings::bool('mail.notify_admin')) {
            $adminTo = Settings::string('moderation.notify_email') ?: Settings::string('site.contact_email');
            if ($adminTo !== '' && filter_var($adminTo, FILTER_VALIDATE_EMAIL)) {
                $adminLocale = (string) Settings::get('ui.default_locale', 'en');
                self::sendTemplate($adminTo, $event, $varsFor($adminLocale), $adminLocale);
            }
        }
    }

    /** The status label as one recipient language sees it. */
    private static function statusLabelIn(string $locale, string $status): string
    {
        $previous = I18n::locale();
        $match = I18n::bestMatch($locale);
        if ($match !== null) {
            I18n::setLocale($match);
        }
        $label = \Athenaeum\Models\Paper::statusLabel($status);
        if ($match !== null) {
            I18n::setLocale($previous);
        }
        return $label;
    }

    /** Admin panel "send a test message" button. */
    public static function sendTest(string $to): array
    {
        return self::sendTemplate($to, self::EVENT_TEST, [
            'site'    => Settings::siteName(),
            'host'    => Settings::string('mail.host') . ':' . Settings::int('mail.port', 465),
            'contact' => Settings::string('site.contact_email'),
            'time'    => gmdate('Y-m-d H:i:s') . ' UTC',
        ], (string) Settings::get('ui.default_locale', 'en'));
    }
}
