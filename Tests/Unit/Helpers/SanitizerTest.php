<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Transfiguration\Helpers\Sanitizer;

class SanitizerTest extends TestCase
{
    public function testHtmlEscapesTags(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            Sanitizer::html('<script>alert(1)</script>')
        );
    }

    public function testHtmlPreservesSafeText(): void
    {
        $this->assertSame('Hello, World!', Sanitizer::html('Hello, World!'));
    }

    public function testHtmlEscapesAmpersand(): void
    {
        $this->assertSame('a &amp; b', Sanitizer::html('a & b'));
    }

    public function testHtmlEscapesQuotes(): void
    {
        $this->assertSame('&quot;x&quot;', Sanitizer::html('"x"'));
    }

    public function testAttrSameAsHtml(): void
    {
        $input = '<a href="x" onclick="alert(1)">';
        $this->assertSame(Sanitizer::html($input), Sanitizer::attr($input));
    }

    public function testJsEncodesString(): void
    {
        $escaped = Sanitizer::js("hello\nworld");
        $this->assertStringContainsString('hello', $escaped);
        $this->assertStringContainsString('world', $escaped);
        // Should be valid JSON.
        $this->assertSame("hello\nworld", json_decode($escaped));
    }

    public function testJsHandlesQuotes(): void
    {
        $escaped = Sanitizer::js("it's \"great\"");
        $this->assertSame("it's \"great\"", json_decode($escaped));
    }

    public function testUrlEncodes(): void
    {
        $this->assertSame('hello+world', Sanitizer::url('hello world'));
    }

    public function testUrlEncodesSpecialChars(): void
    {
        $this->assertStringContainsString('%2F', Sanitizer::url('/path'));
    }
}
