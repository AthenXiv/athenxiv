<?php

declare(strict_types=1);

namespace Athenaeum\Core;

/**
 * Synchroniser-token style CSRF protection for every state changing request.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(): bool
    {
        $expected = Session::get(self::KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $given = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($given) || $given === '') {
            $json = App::request()->json();
            $given = is_string($json['_token'] ?? null) ? $json['_token'] : '';
        }
        return is_string($given) && $given !== '' && hash_equals($expected, $given);
    }

    public static function rotate(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }
}
