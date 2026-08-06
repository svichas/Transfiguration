<?php

declare(strict_types=1);

namespace Transfiguration\Helpers;

/**
 * Manages template variable bindings and delegates expression
 * evaluation to the safe ExpressionParser.
 */
class Evaluator
{
    /**
     * @param array<string, mixed> $data Initial variable bindings.
     */
    public function __construct(private array $data = []) {}

    // ────────────────────────────────────────────────────────
    // Data management
    // ────────────────────────────────────────────────────────

    /**
     * Merge additional data into the store.
     */
    public function appendData(array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Return all current bindings.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    // ────────────────────────────────────────────────────────
    // Expression evaluation
    // ────────────────────────────────────────────────────────

    /**
     * Safely evaluate a template expression against the data store
     * merged with optional scoped data.
     */
    public function evaluate(string $expression, array $data = []): mixed
    {
        $merged = array_merge($this->data, $data);
        return (new ExpressionParser())->evaluate($expression, $merged);
    }

    // ────────────────────────────────────────────────────────
    // Variable helpers
    // ────────────────────────────────────────────────────────

    /**
     * Assign a value to a template variable.
     */
    public function setVar(string $varname = '', string $varcontent = '', array $tokenData = []): bool
    {
        $varname = $this->evalVarName($varname, true);
        $this->data[$varname] = $this->evaluate($varcontent, $tokenData);
        return true;
    }

    /**
     * Normalise a variable name: strip spaces and optional $ prefix.
     */
    public function evalVarName(string $varname = '', bool $removeDollar = false): string
    {
        $varname = str_replace(' ', '', $varname);
        if ($removeDollar) {
            $varname = ltrim($varname, '$');
        }
        return $varname;
    }
}
