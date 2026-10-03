<?php

declare(strict_types=1);

namespace Athenaeum\Controllers;

use Athenaeum\Core\Auth;
use Athenaeum\Core\Request;
use Athenaeum\Core\Response;
use Athenaeum\Core\Router;
use Athenaeum\Core\Session;
use Athenaeum\Core\Settings;
use Athenaeum\Core\Validator;
use Athenaeum\Core\View;

/**
 * Base controller with the handful of conveniences every controller needs.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): Response
    {
        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        if (!preg_match('#^https?://#i', $url)) {
            $url = url($url);
        }
        return Response::redirect($url, $status);
    }

    protected function back(string $fallback = '/'): Response
    {
        return $this->redirect(\Athenaeum\Core\App::request()->referer($fallback));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /**
     * Validates and, on failure, flashes the errors + input and redirects back.
     * Controllers that need the errors inline can use Validator directly.
     */
    protected function validateOrBack(Request $request, array $rules, string $backUrl = '/'): ?Response
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->passes()) {
            return null;
        }
        Session::flash('errors', $validator->errors());
        Session::flash('error', $validator->firstError());
        Session::flashInput($request->all());
        return $this->redirect($backUrl);
    }

    protected function requireUser(): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw new \RuntimeException('Authentication required.');
        }
        return $user;
    }

    protected function setting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }

    /** Absolute URL to a named route. */
    protected function route(string $name, array $params = []): string
    {
        return Router::url($name, $params);
    }
}
