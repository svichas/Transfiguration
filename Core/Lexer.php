<?php

declare(strict_types=1);

namespace Transfiguration\Core;

/**
 * Class for translating plain text (Transfiguration code) to tokens.
 */
class Lexer
{
    /** @var array<string, string> Block-type to end-keyword mapping. */
    private $blockEnds = [
        'FOR' => 'ENDFOR',
        'IF'  => 'ENDIF',
    ];

    /** @var string Raw input HTML/template. */
    private $html = '';

    /** @var array<int, array{type: string, content: string, tab: int}> Generated tokens. */
    private $tokens = [];

    /** @var int Current nesting depth for closing tags. */
    private $tab = 0;

    /**
     * @param string $html Template source to lex.
     */
    public function __construct(string $html = '')
    {
        $this->html = $html;
        $this->createTokens();
    }

    /**
     * Translate plain text (Transfiguration code) into tokens.
     */
    public function createTokens(): void
    {
        $openCodeBlock = false;
        $skipNext = false;
        $openCommentBlock = false;

        $strlen = strlen($this->html);
        $block = '';

        for ($i = 0; $i < $strlen; $i++) {
            $char = $this->html[$i];
            $nextChar = ($i + 1 < $strlen) ? $this->html[$i + 1] : '';

            // Skip next character (used after consuming a two-char delimiter).
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            // Open code block: {{
            if (!$openCodeBlock && !$openCommentBlock && $char === '{' && $nextChar === '{') {
                $skipNext = true;
                $openCodeBlock = true;
                $this->createToken('HTML', $block);
                $block = '';
                continue;
            }

            // Close code block: }}
            if ($openCodeBlock && !$openCommentBlock && $char === '}' && $nextChar === '}') {
                $skipNext = true;
                $openCodeBlock = false;
                $this->doToken($block);
                $block = '';
                continue;
            }

            // Open comment block: {#
            if (!$openCodeBlock && !$openCommentBlock && $char === '{' && $nextChar === '#') {
                $skipNext = true;
                $openCommentBlock = true;
                continue;
            }

            // Close comment block: #}
            if ($openCommentBlock && $char === '#' && $nextChar === '}') {
                $skipNext = true;
                $openCommentBlock = false;
                continue;
            }

            // Accumulate characters when not inside a comment.
            if (!$openCommentBlock) {
                $block .= $char;
            }
        }

        // Flush any remaining content as HTML.
        if ($block !== '') {
            $this->createToken('HTML', $block);
        }
    }

    /**
     * Return the generated tokens.
     *
     * @return array<int, array{type: string, content: string, tab: int}>
     */
    public function exportTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Create a single token and append it to the list.
     */
    private function createToken(string $type = '', string $content = ''): bool
    {
        $type = strtoupper($type);
        $currentTab = $this->tab;

        // Handle tab nesting for block openers.
        if (isset($this->blockEnds[$type])) {
            $this->tab++;
        }

        // Handle tab nesting for block closers.
        if (in_array($type, $this->blockEnds, true) && $currentTab > 0) {
            $this->tab--;
            $currentTab--;
        }

        // Special case: ELSE / ELSEIF are at the same level as the IF.
        if ($type === 'ELSE' || $type === 'ELSEIF') {
            $currentTab = max(0, $currentTab - 1);
        }

        if ($content === '' && $type === 'HTML') {
            return true; // skip empty HTML tokens
        }

        $this->tokens[] = [
            'type'    => $type,
            'content' => $content,
            'tab'     => $currentTab,
        ];

        return true;
    }

    /**
     * Create a token from a code block's raw content.
     * Skips empty blocks like {{ }}.
     */
    private function doToken(string $content = ''): void
    {
        $content = trim($content);

        if ($content === '') {
            return; // skip {{ }}
        }

        $tokenType    = $this->findCodeType($content);
        $tokenContent = $this->findCodeContent($content);
        $this->createToken($tokenType, $tokenContent);
    }

    /**
     * Extract the token type (first word) from a code block.
     */
    private function findCodeType(string $codeBlock): string
    {
        $codeBlock = trim($codeBlock);
        $parts = explode(' ', $codeBlock, 2);
        return strtoupper($parts[0]);
    }

    /**
     * Extract the token content (everything after the first word) from a code block.
     */
    private function findCodeContent(string $codeBlock): string
    {
        $codeBlock = trim($codeBlock);
        $parts = explode(' ', $codeBlock, 2);
        return isset($parts[1]) ? trim($parts[1]) : '';
    }
}
