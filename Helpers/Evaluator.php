<?php

declare(strict_types=1);

namespace Transfiguration\Helpers;

/**
 * Evaluator class for evaluating string expressions safely.
 *
 * Replaces raw eval() with a token-based approach that only allows
 * a whitelist of safe operations (variable access, string concat,
 * arithmetic, comparisons, and a limited set of built-in functions).
 */
class Evaluator
{
    /**
     * Variable bindings available in template expressions.
     *
     * @var array<string, mixed>
     */
    private $data = [];

    /**
     * Whitelist of safe PHP functions callable from templates.
     */
    private const SAFE_FUNCTIONS = [
        'count', 'strlen', 'strtoupper', 'strtolower', 'ucfirst',
        'trim', 'ltrim', 'rtrim', 'date', 'number_format',
        'round', 'ceil', 'floor', 'abs', 'min', 'max',
        'implode', 'explode', 'substr', 'str_replace',
        'htmlspecialchars', 'htmlentities', 'strip_tags',
        'urlencode', 'urldecode', 'json_encode', 'json_decode',
        'empty', 'isset', 'is_array', 'is_string', 'is_numeric',
        'intval', 'floatval', 'strval', 'boolval',
    ];

    /**
     * @param array<string, mixed> $data Initial variable bindings.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Append additional data to the evaluator's data store.
     *
     * @param array<string, mixed> $data
     */
    public function appendData(array $data = []): void
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * Safely evaluate a template expression.
     *
     * Only allows: variable references ($var, $var.key), string/number
     * literals, basic operators (., +, -, *, /, %, ==, !=, ===, !==,
     * <, >, <=, >=, &&, ||, !), ternary, and a whitelist of functions.
     *
     * @param string $expression The expression to evaluate.
     * @param array<string, mixed> $data Additional scoped data.
     * @return mixed The result of the evaluation, or false on error.
     */
    public function evaluate(string $expression, array $data = [])
    {
        if (empty($expression)) {
            return false;
        }

        // Merge global and scoped data for variable resolution.
        $mergedData = array_merge($this->data, $data);

        // Only take the first statement (semicolon delimiter).
        $expressionArr = explode(';', $expression, 2);
        $expression = trim($expressionArr[0]);

        try {
            return $this->safeEval($expression, $mergedData);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Core safe evaluation using a simple recursive-descent approach.
     *
     * @param string $expression
     * @param array<string, mixed> $data
     * @return mixed
     * @throws \RuntimeException On unsafe constructs.
     */
    private function safeEval(string $expression, array $data)
    {
        $expression = trim($expression);

        if ($expression === '') {
            return '';
        }

        // 1. Try to parse as a ternary expression: condition ? trueVal : falseVal
        $result = $this->parseTernary($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 2. Try logical OR / AND (lowest precedence after ternary)
        $result = $this->parseLogical($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 3. Try comparison operators
        $result = $this->parseComparison($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 4. Try string concatenation
        $result = $this->parseConcatenation($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 5. Try arithmetic
        $result = $this->parseArithmetic($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 6. Try unary ! (negation)
        if ($expression[0] === '!') {
            $inner = trim(substr($expression, 1));
            return !$this->safeEval($inner, $data);
        }

        // 7. Try function call
        $result = $this->parseFunctionCall($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 8. Try variable reference
        $result = $this->parseVariable($expression, $data);
        if ($result[0]) {
            return $result[1];
        }

        // 9. Try string literal (single or double quoted)
        $result = $this->parseStringLiteral($expression);
        if ($result[0]) {
            return $result[1];
        }

        // 10. Try numeric literal
        if (is_numeric($expression)) {
            return $expression + 0; // convert to int or float
        }

        // 11. Try boolean/null literals
        $lower = strtolower($expression);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        // 12. Parenthesized sub-expression
        if ($expression[0] === '(' && $expression[strlen($expression) - 1] === ')') {
            return $this->safeEval(substr($expression, 1, -1), $data);
        }

        // If nothing matched, return the raw string.
        return $expression;
    }

    /**
     * Parse ternary: condition ? trueVal : falseVal
     *
     * @return array{0: bool, 1: mixed}
     */
    private function parseTernary(string $expr, array $data): array
    {
        // Find top-level ? and : (not nested inside parens or strings)
        $qPos = $this->findTopLevelOperator($expr, '?');
        if ($qPos === false) {
            return [false, null];
        }

        $colonPos = $this->findTopLevelOperator($expr, ':', $qPos + 1);
        if ($colonPos === false) {
            return [false, null];
        }

        $condition = trim(substr($expr, 0, $qPos));
        $trueVal = trim(substr($expr, $qPos + 1, $colonPos - $qPos - 1));
        $falseVal = trim(substr($expr, $colonPos + 1));

        $condResult = $this->safeEval($condition, $data);
        return [true, $condResult ? $this->safeEval($trueVal, $data) : $this->safeEval($falseVal, $data)];
    }

    /**
     * Parse logical || and &&
     */
    private function parseLogical(string $expr, array $data): array
    {
        $orPos = $this->findTopLevelOperator($expr, '||');
        $andPos = $this->findTopLevelOperator($expr, '&&');

        if ($orPos !== false && ($andPos === false || $orPos < $andPos)) {
            $left = $this->safeEval(trim(substr($expr, 0, $orPos)), $data);
            if ($left) {
                return [true, $left];
            }
            return [true, $this->safeEval(trim(substr($expr, $orPos + 2)), $data)];
        }

        if ($andPos !== false) {
            $left = $this->safeEval(trim(substr($expr, 0, $andPos)), $data);
            if (!$left) {
                return [true, $left];
            }
            return [true, $this->safeEval(trim(substr($expr, $andPos + 2)), $data)];
        }

        return [false, null];
    }

    /**
     * Parse comparison operators: ==, !=, ===, !==, <, >, <=, >=
     */
    private function parseComparison(string $expr, array $data): array
    {
        $ops = ['===', '!==', '==', '!=', '<=', '>=', '<', '>'];
        foreach ($ops as $op) {
            $pos = $this->findTopLevelOperator($expr, $op);
            if ($pos !== false) {
                $left = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
                $right = $this->safeEval(trim(substr($expr, $pos + strlen($op))), $data);
                return [true, $this->compare($left, $op, $right)];
            }
        }
        return [false, null];
    }

    /**
     * Compare two values with a given operator.
     */
    private function compare($left, string $op, $right): bool
    {
        switch ($op) {
            case '==':  return $left == $right;
            case '!=':  return $left != $right;
            case '===': return $left === $right;
            case '!==': return $left !== $right;
            case '<':   return $left < $right;
            case '>':   return $left > $right;
            case '<=':  return $left <= $right;
            case '>=':  return $left >= $right;
            default:    return false;
        }
    }

    /**
     * Parse string concatenation with . operator
     */
    private function parseConcatenation(string $expr, array $data): array
    {
        $pos = $this->findTopLevelOperator($expr, '.');
        if ($pos === false) {
            return [false, null];
        }

        $left = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
        $right = $this->safeEval(trim(substr($expr, $pos + 1)), $data);
        return [true, (string)$left . (string)$right];
    }

    /**
     * Parse arithmetic: +, -, *, /, %
     */
    private function parseArithmetic(string $expr, array $data): array
    {
        // Check in order of precedence (lowest first): +, -, then *, /, %
        $addPos = $this->findTopLevelOperator($expr, '+');
        $subPos = $this->findTopLevelOperator($expr, '-');

        // For subtraction, skip if it looks like a negative sign at start or after operator
        if ($subPos !== false && $subPos === 0) {
            $subPos = $this->findTopLevelOperator($expr, '-', 1);
        }
        // Also skip minus signs that are preceded by ( [ . + - * / %
        if ($subPos !== false && $subPos > 0) {
            $prev = $expr[$subPos - 1];
            if (in_array($prev, ['(', '[', '.', '+', '-', '*', '/', '%', '?', ':'], true)) {
                $subPos = $this->findTopLevelOperator($expr, '-', $subPos + 1);
            }
        }

        if ($addPos !== false || $subPos !== false) {
            $pos = ($addPos !== false && ($subPos === false || $addPos < $subPos)) ? $addPos : $subPos;
            $left = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
            $right = $this->safeEval(trim(substr($expr, $pos + 1)), $data);
            $op = ($pos === $addPos) ? '+' : '-';
            return [true, $op === '+' ? $left + $right : $left - $right];
        }

        // *, /, %
        $mulPos = $this->findTopLevelOperator($expr, '*');
        $divPos = $this->findTopLevelOperator($expr, '/');
        $modPos = $this->findTopLevelOperator($expr, '%');

        $pos = false;
        $op = '';
        foreach (['*', '/', '%'] as $o) {
            $p = $this->findTopLevelOperator($expr, $o);
            if ($p !== false && ($pos === false || $p < $pos)) {
                $pos = $p;
                $op = $o;
            }
        }

        if ($pos !== false) {
            $left = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
            $right = $this->safeEval(trim(substr($expr, $pos + 1)), $data);
            if ($op === '*') return [true, $left * $right];
            if ($op === '/') return [true, ($right != 0) ? $left / $right : 0];
            if ($op === '%') return [true, ($right != 0) ? $left % $right : 0];
        }

        return [false, null];
    }

    /**
     * Parse a function call: funcName(args)
     */
    private function parseFunctionCall(string $expr, array $data): array
    {
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\(\s*(.*)\s*\)$/', $expr, $matches)) {
            return [false, null];
        }

        $funcName = $matches[1];

        if (!in_array($funcName, self::SAFE_FUNCTIONS, true)) {
            // Not a safe function; treat as literal.
            return [false, null];
        }

        $argsStr = $matches[2];

        // Parse arguments
        $args = $this->parseArguments($argsStr, $data);

        try {
            return [true, $funcName(...$args)];
        } catch (\Throwable $e) {
            return [false, null];
        }
    }

    /**
     * Parse comma-separated function arguments.
     *
     * @return array<int, mixed>
     */
    private function parseArguments(string $argsStr, array $data): array
    {
        if (trim($argsStr) === '') {
            return [];
        }

        $args = [];
        $depth = 0;
        $current = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0, $len = strlen($argsStr); $i < $len; $i++) {
            $ch = $argsStr[$i];

            if ($inString) {
                $current .= $ch;
                if ($ch === $stringChar && ($i === 0 || $argsStr[$i - 1] !== '\\')) {
                    $inString = false;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                $current .= $ch;
                continue;
            }

            if ($ch === '(' || $ch === '[') {
                $depth++;
                $current .= $ch;
                continue;
            }

            if ($ch === ')' || $ch === ']') {
                $depth--;
                $current .= $ch;
                continue;
            }

            if ($ch === ',' && $depth === 0) {
                $args[] = $this->safeEval(trim($current), $data);
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        if (trim($current) !== '') {
            $args[] = $this->safeEval(trim($current), $data);
        }

        return $args;
    }

    /**
     * Parse a variable reference like $var, $var.key, $var['key']
     */
    private function parseVariable(string $expr, array $data): array
    {
        if (!preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/', $expr, $matches)) {
            return [false, null];
        }

        $varName = $matches[1];
        $rest = $matches[2] ?? '';

        if (!array_key_exists($varName, $data)) {
            return [true, null];
        }

        $value = $data[$varName];

        // Handle array/object access: .key or ['key'] or [index]
        while ($rest !== '') {
            if (preg_match('/^\.([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/', $rest, $m)) {
                $key = $m[1];
                $rest = $m[2];
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } elseif (is_object($value) && isset($value->{$key})) {
                    $value = $value->{$key};
                } else {
                    return [true, null];
                }
            } elseif (preg_match('/^\[([^\]]+)\](.*)$/', $rest, $m)) {
                $key = trim($m[1], "'\"");
                $rest = $m[2];
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } elseif (is_object($value) && isset($value->{$key})) {
                    $value = $value->{$key};
                } else {
                    return [true, null];
                }
            } else {
                break;
            }
        }

        return [true, $value];
    }

    /**
     * Parse a string literal (single or double quoted).
     */
    private function parseStringLiteral(string $expr): array
    {
        $len = strlen($expr);
        if ($len < 2) {
            return [false, null];
        }

        $quote = $expr[0];
        if (!in_array($quote, ["'", '"'], true)) {
            return [false, null];
        }

        if ($expr[$len - 1] !== $quote) {
            return [false, null];
        }

        $inner = substr($expr, 1, -1);
        // Unescape simple sequences
        $inner = str_replace(['\\' . $quote, '\\\\'], [$quote, '\\'], $inner);

        return [true, $inner];
    }

    /**
     * Find a top-level operator position (not inside parens, brackets, or strings).
     *
     * @param string $expr
     * @param string $op
     * @param int $start
     * @return int|false
     */
    private function findTopLevelOperator(string $expr, string $op, int $start = 0)
    {
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $opLen = strlen($op);

        for ($i = $start, $len = strlen($expr) - $opLen + 1; $i < $len; $i++) {
            $ch = $expr[$i];

            if ($inString) {
                if ($ch === $stringChar && ($i === 0 || $expr[$i - 1] !== '\\')) {
                    $inString = false;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                continue;
            }

            if ($ch === '(' || $ch === '[') {
                $depth++;
                continue;
            }

            if ($ch === ')' || $ch === ']') {
                $depth--;
                continue;
            }

            if ($depth === 0 && substr($expr, $i, $opLen) === $op) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Get the current data bindings (used by Parser for sub-template data merging).
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set a template variable.
     *
     * @param string $varname
     * @param string $varcontent
     * @param array<string, mixed> $tokenData
     * @return bool
     */
    public function setVar(string $varname = '', string $varcontent = '', array $tokenData = []): bool
    {
        $varname = $this->evalVarName($varname, true);
        $this->data[$varname] = $this->evaluate($varcontent, $tokenData);
        return true;
    }

    /**
     * Format a variable name by removing spaces and optional $ prefix.
     *
     * @param string $varname
     * @param bool $removeDollar
     * @return string
     */
    public function evalVarName(string $varname = '', bool $removeDollar = false): string
    {
        $varname = str_replace(' ', '', $varname);
        if ($removeDollar) {
            $varname = ltrim($varname, '$');
        }
        return $varname;
    }

    /**
     * Process a FOR loop by cloning tokens for each iteration.
     *
     * @param array<int, array> $forTokens
     * @param string $forArray
     * @param string $forVar
     * @param string $forKey
     * @return array<int, array>
     */
    public function doFor(array $forTokens = [], string $forArray = '', string $forVar = '', string $forKey = ''): array
    {
        $forArray = $this->evalVarName($forArray, true);
        $forVar   = $this->evalVarName($forVar, true);
        $forKey   = $this->evalVarName($forKey, true);

        $returnForTokens = [];

        if (!isset($this->data[$forArray]) || !is_iterable($this->data[$forArray])) {
            return $returnForTokens;
        }

        foreach ($this->data[$forArray] as $__key__ => $__var__) {
            $iterTokens = $forTokens;
            $count = count($iterTokens);

            for ($i = 0; $i < $count; $i++) {
                $iterTokens[$i]['data'] = [
                    $forVar => $__var__,
                ];
                if ($forKey !== '') {
                    $iterTokens[$i]['data'][$forKey] = $__key__;
                }
            }

            foreach ($iterTokens as $tok) {
                $returnForTokens[] = $tok;
            }
        }

        return $returnForTokens;
    }
}
