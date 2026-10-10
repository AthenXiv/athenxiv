<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\Auth;
use Athenaeum\Core\Config;
use Athenaeum\Core\Csrf;
use Athenaeum\Core\Logger;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Router;
use Athenaeum\Core\Session;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Validator;
use Athenaeum\Models\AuditLog;
use Athenaeum\Models\EmailVerification;
use Athenaeum\Models\User;
use Athenaeum\Services\Mailer;

final class AuthController extends Controller
{
    public function loginForm(Request $request): Response
    {
        return $this->view('auth/login', [
            'title'   => __('auth.login_title'),
            'heading' => __('auth.login_title'),
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|max:190',
            'password' => 'required|string|max:200',
        ]);
        if ($validator->fails()) {
            Session::flash('error', $validator->firstError());
            Session::flashInput($request->all());
            return $this->redirect(Router::url('login'));
        }

        $email = $request->str('email');
        $result = Auth::attempt($email, (string) $request->input('password', ''));

        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            Session::flashInput(['email' => $email]);
            return $this->redirect(Router::url('login'));
        }

        Csrf::rotate();
        AuditLog::record('auth.login', 'user', Auth::id());

        $intended = Session::pull('intended_url');
        if (is_string($intended) && str_starts_with($intended, '/')) {
            return $this->redirect($intended);
        }
        return $this->redirect(Router::url(Auth::isAdmin() ? 'admin.dashboard' : 'dashboard'));
    }

    public function registerForm(Request $request): Response
    {
        if (!Settings::bool('registration.open')) {
            Session::flash('error', __('auth.registration_closed'));
            return $this->redirect(Router::url('login'));
        }
        return $this->view('auth/register', [
            'title'        => __('auth.register_title'),
            'heading'      => __('auth.register_title'),
            'minPassword'  => (int) Config::get('security.password_min_length', 10),
            'enabled'      => true,
            'verifyEmail'  => self::verificationRequired(),
        ], 'layouts/auth');
    }

    /**
     * Should registration demand an e-mailed code?
     *
     * Only when the administrator asked for it *and* the site can actually send
     * mail — otherwise a broken SMTP setting would lock everybody out of
     * registering.
     */
    public static function verificationRequired(): bool
    {
        if (!Settings::bool('registration.verify_email', true)) {
            return false;
        }
        return Mailer::configured()['ok'];
    }

    /** POST /register/code — send (or re-send) the verification code. */
    public function sendCode(Request $request): Response
    {
        $email = mb_strtolower(trim($request->str('email')));
        $wantsJson = str_contains((string) $request->header('Accept'), 'application/json');

        $fail = function (string $message, int $status = 422) use ($wantsJson): Response {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'error' => $message], $status);
            }
            Session::flash('error', $message);
            Session::flashInput(['email' => $email]);
            return $this->redirect(Router::url('register'));
        };

        if (!Settings::bool('registration.open')) {
            return $fail(__('auth.registration_closed'));
        }
        if (!self::verificationRequired()) {
            return $fail(__('auth.mail_unavailable'));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fail(__('validation.email', ['field' => __('auth.email')]));
        }
        if (User::findByEmail($email) !== null) {
            return $fail(__('validation.unique', ['field' => __('auth.email')]));
        }

        $issued = EmailVerification::issue($email, EmailVerification::PURPOSE_REGISTER);
        if (empty($issued['ok'])) {
            $retry = (int) ($issued['retry_after'] ?? 0);
            $message = ($issued['error'] ?? '') === 'too soon'
                ? __('auth.code_sent', ['email' => $email])
                : __('auth.code_invalid');
            // A rate-limited request is not an error the visitor can act on
            // beyond waiting, so keep the wording calm and specific.
            return $fail($retry > 0 && ($issued['error'] ?? '') !== 'too soon'
                ? __('auth.code_sent', ['email' => $email])
                : $message);
        }

        // The message follows the language the visitor is reading the site in,
        // not a value posted by the form: a page rendered before a language
        // switch would otherwise send its stale language.
        $sent = Mailer::sendTemplate($email, 'verify_code', [
            'code' => (string) $issued['code'],
            'site' => Settings::string('site.name', 'AthenXiv'),
        ], locale());

        AuditLog::record('auth.code_sent', 'email', null, [
            'email' => $email,
            'ok'    => !empty($sent['ok']),
        ]);

        if (empty($sent['ok'])) {
            // Never leak SMTP internals to an anonymous visitor; log them.
            Logger::warning('verification mail failed', ['email' => $email, 'error' => $sent['error'] ?? '']);
            return $fail(__('auth.code_mail_failed'));
        }

        if ($wantsJson) {
            return Response::json([
                'ok'      => true,
                'message' => __('auth.code_sent', ['email' => $email]),
            ]);
        }
        Session::flash('success', __('auth.code_sent', ['email' => $email]));
        Session::flashInput(['email' => $email]);
        return $this->redirect(Router::url('register'));
    }

    public function register(Request $request): Response
    {
        if (!Settings::bool('registration.open')) {
            Session::flash('error', __('auth.registration_closed'));
            return $this->redirect(Router::url('register'));
        }

        $min = (int) Config::get('security.password_min_length', 10);
        $rules = [
            'email'      => 'required|email|max:190|unique:users,email',
            'nickname'   => 'required|string|min:2|max:80',
            'password'   => 'required|string|min:' . $min . '|max:200|same:password_confirmation',
            'terms'      => 'accepted',
        ];
        if (self::verificationRequired()) {
            $rules['email_code'] = 'required|string|min:6|max:6';
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flash('error', $validator->firstError());
            Session::flashInput($request->all());
            return $this->redirect(Router::url('register'));
        }

        if (self::verificationRequired()) {
            $check = EmailVerification::verify(
                $request->str('email'),
                $request->str('email_code'),
                EmailVerification::PURPOSE_REGISTER
            );
            if (empty($check['ok'])) {
                Session::flash('errors', ['email_code' => __('auth.code_invalid')]);
                Session::flash('error', __('auth.code_invalid'));
                Session::flashInput($request->all());
                AuditLog::record('auth.code_failed', 'email', null, ['email' => $request->str('email')]);
                return $this->redirect(Router::url('register'));
            }
        }

        $id = User::register([
            'email'        => $request->str('email'),
            'password'     => (string) $request->input('password', ''),
            'nickname'     => $request->str('nickname'),
            'display_name' => $request->str('nickname'),
            'affiliation'  => $request->str('affiliation') ?: null,
            'locale'       => $request->str('locale') ?: null,
            'role'         => Settings::string('registration.default_role', 'user'),
        ]);

        $user = User::find($id);
        if ($user !== null) {
            Auth::login($user);
        }
        Csrf::rotate();
        AuditLog::record('auth.register', 'user', $id);

        Session::flash('success', __('auth.register_welcome', ['name' => (string) $request->str('nickname')]));
        return $this->redirect(Router::url('dashboard'));
    }

    /**
     * May a visitor ask for a password-reset code?
     *
     * The flow depends on outgoing mail, so on a site whose SMTP settings are
     * still empty the link is hidden and the endpoints answer with a pointer to
     * the contact address instead of a form that could never deliver anything.
     */
    public static function resetAvailable(): bool
    {
        if (!Settings::bool('registration.reset_password', true)) {
            return false;
        }
        return Mailer::configured()['ok'];
    }

    /** GET /password/forgot — the "e-mail me a reset code" page. */
    public function forgotForm(Request $request): Response
    {
        return $this->view('auth/forgot-password', [
            'title'     => __('auth.reset_title'),
            'heading'   => __('auth.reset_title'),
            'available' => self::resetAvailable(),
            'minPassword' => (int) Config::get('security.password_min_length', 10),
            'expiresMinutes' => (int) round(EmailVerification::TTL_SECONDS / 60),
        ], 'layouts/auth');
    }

    /**
     * POST /password/forgot — send a reset code (AJAX) or fall back to a plain
     * form post. Answers identically whether or not the address has an account:
     * a public endpoint that reports "no such user" is an address-harvesting
     * oracle, and the wording chosen says "if an account exists" for that reason.
     */
    public function sendResetCode(Request $request): Response
    {
        $email = mb_strtolower(trim($request->str('email')));
        $wantsJson = str_contains((string) $request->header('Accept'), 'application/json');

        $fail = function (string $message, int $status = 422) use ($wantsJson, $email, $request): Response {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'error' => $message], $status);
            }
            Session::flash('error', $message);
            Session::flashInput(['email' => $email]);
            return $this->redirect(Router::url('password.request'));
        };

        if (!self::resetAvailable()) {
            return $fail(__('auth.reset_unavailable'));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $fail(__('validation.email', ['field' => __('auth.email')]));
        }

        // Cheap per-IP ceiling: without it one script could walk an address list
        // and have the site deliver a code for each entry.
        if (!$this->withinResetRateLimit()) {
            Logger::warning('password reset rate limited', ['ip' => $request->ip()]);
            return $fail(__('auth.code_invalid'), 429);
        }

        $user = User::findByEmail($email);
        if ($user === null || ($user['status'] ?? 'active') === 'banned') {
            // Same reply, no mail. The audit trail keeps the truth.
            AuditLog::record('auth.reset_unknown', 'email', null, ['email' => $email]);
            return $this->resetCodeResponse($wantsJson, $email);
        }

        $issued = EmailVerification::issue($email, EmailVerification::PURPOSE_RESET);
        if (empty($issued['ok'])) {
            $error = (string) ($issued['error'] ?? '');
            if ($error === 'too soon') {
                // A second click inside the cool-down is not a failure worth
                // alarming anyone about: the code from the first click still works.
                return $this->resetCodeResponse($wantsJson, $email);
            }
            Logger::warning('password reset code not issued', ['email' => $email, 'error' => $error]);
            return $fail(__('auth.code_mail_failed'), 429);
        }

        $sent = Mailer::sendTemplate($email, 'reset_code', [
            'site'    => Settings::siteName(),
            'code'    => (string) $issued['code'],
            'minutes' => (string) max(1, (int) round(EmailVerification::TTL_SECONDS / 60)),
            'url'     => Router::url('password.request'),
            'name'    => (string) (($user['display_name'] ?? '') ?: ($user['nickname'] ?? '')),
        ], locale());

        AuditLog::record('auth.reset_sent', 'email', null, [
            'email' => $email,
            'ok'    => !empty($sent['ok']),
        ]);

        if (empty($sent['ok'])) {
            Logger::warning('password reset mail failed', ['email' => $email, 'error' => $sent['error'] ?? '']);
            return $fail(__('auth.code_mail_failed'));
        }

        return $this->resetCodeResponse($wantsJson, $email);
    }

    /** POST /password/reset — swap the code for a new password. */
    public function reset(Request $request): Response
    {
        $email = mb_strtolower(trim($request->str('email')));
        $min = (int) Config::get('security.password_min_length', 10);

        $back = function () use ($email, $request): Response {
            Session::flashInput(['email' => $email]);
            return $this->redirect(Router::url('password.request'));
        };

        if (!self::resetAvailable()) {
            Session::flash('error', __('auth.reset_unavailable'));
            return $back();
        }

        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|max:190',
            'code'     => 'required|string|min:6|max:6',
            'password' => 'required|string|min:' . $min . '|max:200|same:password_confirmation',
        ]);
        if ($validator->fails()) {
            Session::flash('errors', $validator->errors());
            Session::flash('error', $validator->firstError());
            return $back();
        }

        $check = EmailVerification::verify($email, $request->str('code'), EmailVerification::PURPOSE_RESET);
        if (empty($check['ok'])) {
            AuditLog::record('auth.reset_failed', 'email', null, [
                'email'  => $email,
                'reason' => (string) ($check['error'] ?? ''),
            ]);
            Session::flash('errors', ['code' => __('auth.reset_invalid')]);
            Session::flash('error', __('auth.reset_invalid'));
            return $back();
        }

        $user = User::findByEmail($email);
        if ($user === null) {
            // The code was valid for an address whose account disappeared in the
            // meantime (deleted by an administrator). Nothing left to change.
            Session::flash('error', __('auth.reset_invalid'));
            return $back();
        }

        User::updatePassword((int) $user['id'], (string) $request->input('password', ''));
        // A new password deserves a new session id: whoever held the old one is
        // no longer this person. The confirmation has to be written *after* the
        // regeneration, or it would be lost with the discarded session.
        Session::regenerate();
        Csrf::rotate();
        AuditLog::record('auth.reset_done', 'user', (int) $user['id'], ['email' => $email]);

        Session::flash('success', __('auth.reset_done'));
        return $this->redirect(Router::url('login'));
    }

    public function logout(Request $request): Response
    {
        AuditLog::record('auth.logout', 'user', Auth::id());
        Auth::logout();
        Session::destroy();
        Session::start();
        Csrf::rotate();
        Session::flash('success', __('auth.logged_out'));
        return $this->redirect(Router::url('home'));
    }

    /**
     * The single answer given to every reset request, whether or not the address
     * is on file.
     */
    private function resetCodeResponse(bool $wantsJson, string $email): Response
    {
        $message = __('auth.reset_sent', ['email' => $email]);
        if ($wantsJson) {
            return Response::json(['ok' => true, 'message' => $message]);
        }
        Session::flash('success', $message);
        Session::flashInput(['email' => $email]);
        return $this->redirect(Router::url('password.request'));
    }

    /**
     * Per-IP ceiling on reset requests, kept in the session.
     *
     * The per-address limits inside EmailVerification already stop a stranger's
     * inbox being flooded; this stops one client from walking a list of
     * addresses, which those per-address limits cannot see.
     */
    private function withinResetRateLimit(int $max = 15, int $window = 3600): bool
    {
        $now = time();
        $stamps = Session::get('reset_request_times');
        $stamps = is_array($stamps) ? array_values(array_filter(
            $stamps,
            static fn ($stamp): bool => is_int($stamp) && $stamp > $now - $window
        )) : [];

        if (count($stamps) >= $max) {
            return false;
        }
        $stamps[] = $now;
        Session::set('reset_request_times', $stamps);
        return true;
    }
}
