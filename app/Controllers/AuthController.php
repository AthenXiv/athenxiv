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

        $sent = Mailer::sendTemplate($email, 'verify_code', [
            'code' => (string) $issued['code'],
            'site' => Settings::string('site.name', 'AthenXiv'),
        ], $request->str('locale') ?: null);

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
}
