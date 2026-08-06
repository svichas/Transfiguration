<?php

declare(strict_types=1);

namespace Transfiguration;

use Transfiguration\Core\Lexer;
use Transfiguration\Core\Parser;
use Transfiguration\Core\Compiler;
use Transfiguration\Core\Hooks;
use Transfiguration\Core\ASTNodeList;

/**
 * Transfiguration main class — lightweight PHP template engine.
 *
 * Pipeline:  template string → Lexer → Parser → AST → Compiler → HTML
 */
class Transfiguration
{
    /** @var string Base path for include/require resolution. */
    private string $includePath = '';

    /** @var array<string, array{method: callable}> Registered hooks. */
    private array $hooks = [];

    /** @var array<string, mixed> Template variable bindings. */
    private array $data = [];

    /** @var string Raw template HTML. */
    private string $html = '';

    /** @var ASTNodeList|null Cached AST (invalidated on mutation). */
    private ?ASTNodeList $ast = null;

    /**
     * @param string              $html Template source.
     * @param array<string, mixed> $data Template variables.
     * @param string              $path Base path for include/require.
     */
    public function __construct(string $html = '', array $data = [], string $path = '')
    {
        $this->html        = $html;
        $this->data        = $data;
        $this->includePath = $path;
    }

    /**
     * Set the template HTML source.
     */
    public function html(string $html = ''): self
    {
        $this->html = $html;
        $this->ast  = null;
        return $this;
    }

    /**
     * Set template variable bindings.
     *
     * @param array<string, mixed> $data
     */
    public function data(array $data = []): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set the base path for include/require resolution.
     */
    public function requirePath(string $path = ''): self
    {
        $this->includePath = $path;
        $this->ast = null;
        return $this;
    }

    /**
     * Register a hook for a custom token type.
     *
     * @param string   $hook   Token type name (case-insensitive).
     * @param callable $method Callback receiving content string.
     */
    public function hook(string $hook = '', callable $method = null): self
    {
        $this->hooks[strtoupper($hook)] = ['method' => $method];
        $this->ast = null;
        return $this;
    }

    /**
     * Build (or return cached) AST from the template source.
     */
    private function getAST(): ASTNodeList
    {
        if ($this->ast === null) {
            $lexer      = new Lexer($this->html);
            $hooks      = new Hooks($this->hooks);
            $parser     = new Parser($lexer->exportTokens(), $hooks);
            $this->ast  = $parser->parse();
        }

        return $this->ast;
    }

    /**
     * Return the parsed token list (for internal use by include).
     *
     * @return array<int, array>
     */
    public function parserTokens(): array
    {
        // Legacy API — used by Compiler::includeTemplate when
        // recursively including templates.  Return the raw tokens
        // so the caller can parse them with its own Parser+Hooks.
        $lexer = new Lexer($this->html);
        return $lexer->exportTokens();
    }

    /**
     * Export the rendered template as a string.
     */
    public function export(): string
    {
        $compiler = new Compiler($this->data, new Hooks($this->hooks), $this->includePath);
        return $compiler->compile($this->getAST());
    }

    /**
     * Render (echo) the template and return $this for chaining.
     */
    public function render(): self
    {
        echo $this->export();
        return $this;
    }
}