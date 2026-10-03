<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use Athenaeum\Controllers\Controller;
use Throwable;

/**
 * Application kernel: configuration, session, i18n, routing and error
 * handling. Intentionally tiny — this must run on plain shared hosting.
 */
final class App
{
    private static ?Router $router = null;

    private static ?Request $request = null;

    private static bool $booted = false;

    /** @var array<string,Closure> */
    private static array $middleware = [];

    private static ?array $middlewareResult = null;

    public static function boot(array $config): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        Config::load($config);

        if (Config::get('app.env') === 'development' || Config::isDebug()) {
            ini_set('display_errors', '1');
        }

        Session::start();
        self::$request = new Request();

        if (Config::get('security.force_https') && !self::$request->isSecure()) {
            $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
            (new Response('', 301, ['Location' => $target]))->send();
            exit;
        }

        self::registerMiddleware();
        self::$router = new Router();
    }

    private static function registerMiddleware(): void
    {
        self::$middleware['auth'] = static function (Request $request): ?Response {
            if (!Auth::check()) {
                Session::flash('error', __('auth.login_required'));
                Session::set('intended_url', $request->path());
                return Response::redirect(Router::url('login'));
            }
            if (Auth::user()['status'] === 'banned') {
                Auth::logout();
                Session::flash('error', __('auth.account_banned'));
                return Response::redirect(Router::url('login'));
            }
            return null;
        };

        self::$middleware['guest'] = static function (): ?Response {
            if (Auth::check()) {
                return Response::redirect(Router::url('dashboard'));
            }
            return null;
        };

        self::$middleware['admin'] = static function (): ?Response {
            if (!Auth::check()) {
                Session::flash('error', __('auth.login_required'));
                return Response::redirect(Router::url('login'));
            }
            if (!Auth::isAdmin()) {
                return View::error(403, __('common.forbidden'));
            }
            return null;
        };

        self::$middleware['csrf'] = static function (): ?Response {
            if (!Csrf::verify()) {
                return View::error(419, __('common.csrf_failed'));
            }
            return null;
        };
    }

    public static function run(): void
    {
        try {
            $response = self::router()->dispatch(self::$request ?? new Request());
        } catch (Throwable $e) {
            $response = self::handleException($e);
        }

        if ($response instanceof Response) {
            if ($response->isFileResponse()) {
                $response->sendFile();
                return;
            }
            $response->send();
        }
    }

    private static function handleException(Throwable $e): Response
    {
        Logger::error($e->getMessage() . "\n" . $e->getTraceAsString());

        $request = self::$request;
        if ($request !== null && $request->wantsJson()) {
            return Response::json([
                'ok'      => false,
                'error'   => Config::isDebug() ? $e->getMessage() : __('common.server_error'),
                'details' => Config::isDebug() ? $e->getTraceAsString() : null,
            ], 500);
        }

        return View::error(500, Config::isDebug() ? $e->getMessage() : __('common.server_error'));
    }

    public static function router(): Router
    {
        return self::$router ??= new Router();
    }

    public static function request(): Request
    {
        return self::$request ??= new Request();
    }

    /** @param array<string,string> $params */
    public static function runMiddleware(string $name, Request $request, array $params): ?Response
    {
        $callback = self::$middleware[$name] ?? null;
        if ($callback === null) {
            return null;
        }
        $result = $callback($request, $params);
        return $result instanceof Response ? $result : null;
    }

    /**
     * Invoke a route handler. Handlers are either a closure or
     * [Controller::class, 'method']; controllers get the request injected.
     *
     * @param array<string,string> $params
     */
    public static function callHandler(mixed $handler, Request $request, array $params): Response
    {
        if ($handler instanceof \Closure) {
            $result = $handler($request, $params);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            if (!$controller instanceof Controller) {
                throw new \RuntimeException('Invalid controller: ' . (is_string($class) ? $class : gettype($class)));
            }
            $result = $controller->{$method}($request, ...array_values($params));
        } else {
            throw new \RuntimeException('Unsupported route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_string($result)) {
            return Response::html($result);
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        throw new \RuntimeException('Route handler returned an unsupported value.');
    }
}
