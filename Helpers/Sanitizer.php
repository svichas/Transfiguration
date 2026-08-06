<?php

declare(strict_types=1);

namespace Transfiguration\Helpers;

/**
 * Output sanitization utilities to prevent XSS.
 *
 * Usage from templates:
 *   {{ echo sanitizer_html($userInput) }}
 *   {{ echo sanitizer_attr($value) }}
 */
class Sanitizer
{
    /**
     * Escape for safe HTML body output.
     */
    public static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape for safe HTML attribute values.
     */
    public static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape for safe inline JavaScript string literal.
     */
    public static function js(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Escape for safe URL parameter / path segment.
     */
    public static function url(string $value): string
    {
        return urlencode($value);
    }
}
