<?php

declare(strict_types=1);

namespace Transfiguration\Core;

use Transfiguration\Helpers\Evaluator;

/**
 * Walks the AST and produces the final HTML string.
 *
 * The Compiler is the "runtime" of the template engine.  It holds
 * the variable state (via Evaluator), a scope stack for loops,
 * hook callbacks, and the include-path resolver.
 */
class Compiler
{
    /** @var Evaluator */
    private Evaluator $evaluator;

    /** @var Hooks */
    private Hooks $hooks;

    /** @var string Base directory for include/require resolution. */
    private string $basePath;

    /**
     * Scope stack for loop variables.
     * Each entry is array<string, mixed> pushed/popped around FOR bodies.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $scopeStack = [];

    /**
     * @param array<string, mixed> $data     Template variable bindings.
     * @param Hooks                $hooks    Registered hook callbacks.
     * @param string               $basePath Base directory for includes.
     */
    public function __construct(array $data, Hooks $hooks, string $basePath = '')
    {
        $this->evaluator = new Evaluator($data);
        $this->hooks     = $hooks;
        $this->basePath  = $this->normalizePath($basePath);
    }

    // ────────────────────────────────────────────────────────
    // Public entry points
    // ────────────────────────────────────────────────────────

    /**
     * Compile an AST node list into the final HTML string.
     */
    public function compile(ASTNodeList $ast): string
    {
        return $this->compileNodeList($ast);
    }

    /**
     * Compile a node list (used internally by nodes that contain sub-lists).
     */
    public function compileNodeList(ASTNodeList $list): string
    {
        $output = '';

        foreach ($list->children as $node) {
            $output .= $node->compile($this);
        }

        return $output;
    }

    // ────────────────────────────────────────────────────────
    // Expression evaluation (delegates to Evaluator with scoping)
    // ────────────────────────────────────────────────────────

    /**
     * Evaluate a template expression, respecting the current scope stack.
     *
     * @return mixed
     */
    public function evaluate(string $expression)
    {
        $scopedData = $this->flattenScope();
        return $this->evaluator->evaluate($expression, $scopedData);
    }

    /**
     * Assign a value to a template variable.
     */
    public function setVar(string $name, string $valueExpression): void
    {
        $this->evaluator->setVar($name, $valueExpression, $this->flattenScope());
    }

    // ────────────────────────────────────────────────────────
    // Scope management (for FOR loops)
    // ────────────────────────────────────────────────────────

    /**
     * Push a new variable scope (used when entering a FOR iteration).
     *
     * @param array<string, mixed> $vars
     */
    public function pushScope(array $vars): void
    {
        $this->scopeStack[] = $vars;
    }

    /**
     * Pop the most recent variable scope (used when leaving a FOR iteration).
     */
    public function popScope(): void
    {
        array_pop($this->scopeStack);
    }

    /**
     * Merge all scopes into a single data array (innermost wins).
     *
     * @return array<string, mixed>
     */
    private function flattenScope(): array
    {
        $result = [];

        foreach ($this->scopeStack as $scope) {
            $result = array_merge($result, $scope);
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────
    // Include / require
    // ────────────────────────────────────────────────────────

    /**
     * Load, parse, and compile an included template.
     *
     * @param string $pathExpression Expression that evaluates to the relative file path.
     * @return string The compiled HTML of the included template.
     */
    public function includeTemplate(string $pathExpression): string
    {
        $relativePath = (string) $this->evaluate($pathExpression);
        $resolvedPath = $this->resolveIncludePath($relativePath);

        if ($resolvedPath === null || !is_file($resolvedPath)) {
            return '';
        }

        $content = @file_get_contents($resolvedPath);
        if ($content === false) {
            return '';
        }

        // Lex → Parse → Compile the included template.
        $lexer  = new Lexer($content);
        $parser = new Parser($lexer->exportTokens(), $this->hooks);
        $ast    = $parser->parse();

        // Share the same evaluator data so included templates see parent variables.
        // Create a sub-compiler that inherits the current evaluator's data.
        $subCompiler = new self(
            $this->evaluator->getData(),
            $this->hooks,
            $this->basePath
        );

        // Carry over the current scope stack.
        $subCompiler->scopeStack = $this->scopeStack;

        return $subCompiler->compile($ast);
    }

    /**
     * Resolve an include path safely, preventing directory traversal.
     */
    private function resolveIncludePath(string $relativePath): ?string
    {
        $relativePath = str_replace("\0", '', $relativePath);
        $candidatePath = $this->basePath . $relativePath;
        $realPath = realpath($candidatePath);

        if ($realPath === false) {
            return null;
        }

        $baseRealPath = realpath($this->basePath);
        if ($baseRealPath === false) {
            return null;
        }

        if (strpos($realPath, $baseRealPath) !== 0) {
            return null;
        }

        return $realPath;
    }

    // ────────────────────────────────────────────────────────
    // Hooks
    // ────────────────────────────────────────────────────────

    /**
     * Execute a registered hook and return its output.
     */
    public function runHook(string $type, string $content): string
    {
        $evaluated = (string) $this->evaluate($content);
        $result = $this->hooks->checkHooks(
            ['type' => $type, 'content' => $content],
            $evaluated
        );

        return $result !== false ? (string) $result : '';
    }

    // ────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }
}
