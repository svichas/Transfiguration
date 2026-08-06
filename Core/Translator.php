<?php

declare(strict_types=1);

namespace Transfiguration\Core;

/**
 * Class to translate tokens to plain HTML.
 */
class Translator
{
    /**
     * @var array<int, array{type: string, content: string, tab?: int}>
     */
    private $tokens = [];

    /**
     * @param array<int, array{type: string, content: string, tab?: int}> $tokens
     */
    public function __construct(array $tokens = [])
    {
        $this->tokens = $tokens;
    }

    /**
     * Translate tokens into a plain HTML string.
     *
     * @return string
     */
    public function translate(): string
    {
        $html = '';

        foreach ($this->tokens as $token) {
            if (($token['type'] ?? '') !== 'HTML') {
                continue;
            }
            $html .= $token['content'] ?? '';
        }

        return $html;
    }
}