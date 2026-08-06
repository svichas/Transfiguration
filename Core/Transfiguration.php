<?php

declare(strict_types=1);

namespace Transfiguration;

use Transfiguration\Core\Lexer;
use Transfiguration\Core\Translator;
use Transfiguration\Core\Parser;

/**
 * Transfiguration main class — lightweight PHP template engine.
 */
class Transfiguration
{
    /** @var string Base path for include/require resolution. */
    private $includePath = '';

    /** @var Parser|null Cached parser instance. */
    private $parser;

    /** @var array<string, array{method: callable}> Registered hooks. */
    private $hooks = [];

    /** @var array<string, mixed> Template variable bindings. */
    private $data = [];

    /** @var string Raw template HTML. */
    private $html = '';

    /**
     * @param string $html Transfiguration template source.
     * @param array<string, mixed> $data Template variables.
     * @param string $path Base path for include/require.
     */
    public function __construct(string $html = '', array $data = [], string $path = '')
    {
        $this->html = $html;
        $this->data = $data;
        $this->includePath = $path;
    }

    /**
     * Set the template HTML source.
     *
     * @param string $html
     * @return $this
     */
    public function html(string $html = ''): self
    {
        $this->html = $html;
        $this->parser = null; // invalidate cached parser
        return $this;
    }

    /**
     * Set template variable bindings.
     *
     * @param array<string, mixed> $data
     * @return $this
     */
    public function data(array $data = []): self
    {
        $this->data = $data;
        $this->parser = null;
        return $this;
    }

    /**
     * Set the base path for include/require resolution.
     *
     * @param string $path
     * @return $this
     */
    public function requirePath(string $path = ''): self
    {
        $this->includePath = $path;
        $this->parser = null;
        return $this;
    }

    /**
     * Register a hook for a custom token type.
     *
     * @param string $hook Token type name (case-insensitive).
     * @param callable $method Callback receiving content string.
     * @return $this
     */
    public function hook(string $hook = '', callable $method = null): self
    {
        $this->hooks[strtoupper($hook)] = [
            'method' => $method,
        ];
        $this->parser = null;
        return $this;
    }

    /**
     * Parse the template and return the token list.
     *
     * @return array<int, array>
     */
    public function parserTokens(): array
    {
        if ($this->parser === null) {
            $lexer = new Lexer($this->html);
            $this->parser = new Parser(
                $lexer->exportTokens(),
                $this->data,
                $this->includePath,
                $this->hooks
            );
        }
        return $this->parser->exportTokens();
    }

    /**
     * Export the rendered template as a string.
     *
     * @return string
     */
    public function export(): string
    {
        $translator = new Translator($this->parserTokens());
        return $translator->translate();
    }

    /**
     * Render (echo) the template and return $this for chaining.
     *
     * @return $this
     */
    public function render(): self
    {
        $translator = new Translator($this->parserTokens());
        echo $translator->translate();
        return $this;
    }
}