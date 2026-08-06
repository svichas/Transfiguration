<?php

declare(strict_types=1);

namespace Transfiguration\Helpers;

/**
 * Safe recursive-descent expression parser.
 *
 * Evaluates template expressions without using eval().  Only allows
 * a whitelist of built-in functions, variable references, basic
 * operators, and literals.
 */
class ExpressionParser
{
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

    // ────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────

    /**
     * Evaluate an expression against a set of variable bindings.
     */
    public function evaluate(string $expression, array $data = []): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return '';
        }

        // Only the first statement (semicolon delimiter).
        $parts = explode(';', $expression, 2);
        $expression = trim($parts[0]);

        try {
            return $this->safeEval($expression, $data);
        } catch (\Throwable) {
            return false;
        }
    }

    // ────────────────────────────────────────────────────────
    // Recursive descent (ordered by precedence, lowest first)
    // ────────────────────────────────────────────────────────

    private function safeEval(string $expression, array $data): mixed
    {
        $expression = trim($expression);

        if ($expression === '') {
            return '';
        }

        // --- 1. Ternary ---
        $result = $this->parseTernary($expression, $data);
        if ($result[0]) return $result[1];

        // --- 2. Logical ---
        $result = $this->parseLogical($expression, $data);
        if ($result[0]) return $result[1];

        // --- 3. Comparison ---
        $result = $this->parseComparison($expression, $data);
        if ($result[0]) return $result[1];

        // --- 4. Variable (before concatenation so $user.name means property access) ---
        $result = $this->parseVariable($expression, $data);
        if ($result[0]) return $result[1];

        // --- 5. Numeric literal (before concatenation so 3.14 is a float, not "3"."14") ---
        if (is_numeric($expression)) {
            return $expression + 0;
        }

        // --- 6. Boolean / null literals ---
        $lower = strtolower($expression);
        if ($lower === 'true')  return true;
        if ($lower === 'false') return false;
        if ($lower === 'null')  return null;

        // --- 7. Concatenation ---
        $result = $this->parseConcatenation($expression, $data);
        if ($result[0]) return $result[1];

        // --- 8. Arithmetic ---
        $result = $this->parseArithmetic($expression, $data);
        if ($result[0]) return $result[1];

        // --- 9. Unary ! ---
        if ($expression[0] === '!') {
            return !$this->safeEval(trim(substr($expression, 1)), $data);
        }

        // --- 10. Function call ---
        $result = $this->parseFunctionCall($expression, $data);
        if ($result[0]) return $result[1];

        // --- 11. String literal ---
        $result = $this->parseStringLiteral($expression);
        if ($result[0]) return $result[1];

        // --- 12. Parenthesized sub-expression ---
        $len = strlen($expression);
        if ($len >= 2 && $expression[0] === '(' && $expression[$len - 1] === ')') {
            return $this->safeEval(substr($expression, 1, -1), $data);
        }

        // Fallback: return as raw string.
        return $expression;
    }

    // ────────────────────────────────────────────────────────
    // Ternary
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseTernary(string $expr, array $data): array
    {
        $qPos = $this->findTopLevelOperator($expr, '?');
        if ($qPos === false) return [false, null];

        $colonPos = $this->findTopLevelOperator($expr, ':', $qPos + 1);
        if ($colonPos === false) return [false, null];

        $condition = trim(substr($expr, 0, $qPos));
        $trueVal   = trim(substr($expr, $qPos + 1, $colonPos - $qPos - 1));
        $falseVal  = trim(substr($expr, $colonPos + 1));

        $condResult = $this->safeEval($condition, $data);
        return [true, $condResult
            ? $this->safeEval($trueVal, $data)
            : $this->safeEval($falseVal, $data)];
    }

    // ────────────────────────────────────────────────────────
    // Logical
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseLogical(string $expr, array $data): array
    {
        $orPos  = $this->findTopLevelOperator($expr, '||');
        $andPos = $this->findTopLevelOperator($expr, '&&');

        // Short-circuit OR.
        if ($orPos !== false && ($andPos === false || $orPos < $andPos)) {
            $left = $this->safeEval(trim(substr($expr, 0, $orPos)), $data);
            return [true, $left ?: $this->safeEval(trim(substr($expr, $orPos + 2)), $data)];
        }

        // Short-circuit AND.
        if ($andPos !== false) {
            $left = $this->safeEval(trim(substr($expr, 0, $andPos)), $data);
            return [true, $left ? $this->safeEval(trim(substr($expr, $andPos + 2)), $data) : $left];
        }

        return [false, null];
    }

    // ────────────────────────────────────────────────────────
    // Comparison
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseComparison(string $expr, array $data): array
    {
        $ops = ['===', '!==', '==', '!=', '<=', '>=', '<', '>'];

        foreach ($ops as $op) {
            $pos = $this->findTopLevelOperator($expr, $op);
            if ($pos !== false) {
                $left  = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
                $right = $this->safeEval(trim(substr($expr, $pos + strlen($op))), $data);
                return [true, $this->compare($left, $op, $right)];
            }
        }

        return [false, null];
    }

    private function compare(mixed $left, string $op, mixed $right): bool
    {
        return match ($op) {
            '=='  => $left == $right,
            '!='  => $left != $right,
            '===' => $left === $right,
            '!==' => $left !== $right,
            '<'   => $left < $right,
            '>'   => $left > $right,
            '<='  => $left <= $right,
            '>='  => $left >= $right,
            default => false,
        };
    }

    // ────────────────────────────────────────────────────────
    // Concatenation
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseConcatenation(string $expr, array $data): array
    {
        $pos = $this->findTopLevelOperator($expr, '.');
        if ($pos === false) return [false, null];

        $left  = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
        $right = $this->safeEval(trim(substr($expr, $pos + 1)), $data);

        return [true, (string) $left . (string) $right];
    }

    // ────────────────────────────────────────────────────────
    // Arithmetic
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseArithmetic(string $expr, array $data): array
    {
        // --- + and - (lowest arithmetic precedence) ---
        $addPos = $this->findTopLevelOperator($expr, '+');
        $subPos = $this->findTopLevelOperator($expr, '-');

        // Skip unary minus.
        if ($subPos === 0) {
            $subPos = $this->findTopLevelOperator($expr, '-', 1);
        }
        if ($subPos !== false && $subPos > 0) {
            $prev = $expr[$subPos - 1];
            if (str_contains('([.+*-/%?:', $prev)) {
                $subPos = $this->findTopLevelOperator($expr, '-', $subPos + 1);
            }
        }

        if ($addPos !== false || $subPos !== false) {
            $pos = ($addPos !== false && ($subPos === false || $addPos < $subPos)) ? $addPos : $subPos;
            $op  = ($pos === $addPos) ? '+' : '-';
            $left  = $this->safeEval(trim(substr($expr, 0, $pos)), $data);
            $right = $this->safeEval(trim(substr($expr, $pos + 1)), $data);
            return [true, $op === '+' ? $left + $right : $left - $right];
        }

        // --- * / % (higher arithmetic precedence) ---
        foreach (['*', '/', '%'] as $o) {
            $p = $this->findTopLevelOperator($expr, $o);
            if ($p !== false) {
                $left  = $this->safeEval(trim(substr($expr, 0, $p)), $data);
                $right = $this->safeEval(trim(substr($expr, $p + 1)), $data);
                return [true, match ($o) {
                    '*' => $left * $right,
                    '/' => ($right != 0) ? $left / $right : 0,
                    '%' => ($right != 0) ? $left % $right : 0,
                }];
            }
        }

        return [false, null];
    }

    // ────────────────────────────────────────────────────────
    // Function calls
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseFunctionCall(string $expr, array $data): array
    {
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\(\s*(.*)\s*\)$/', $expr, $m)) {
            return [false, null];
        }

        $funcName = $m[1];

        if (!in_array($funcName, self::SAFE_FUNCTIONS, true)) {
            return [false, null];
        }

        try {
            return [true, $funcName(...$this->parseArguments($m[2], $data))];
        } catch (\Throwable) {
            return [false, null];
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function parseArguments(string $argsStr, array $data): array
    {
        if (trim($argsStr) === '') {
            return [];
        }

        $args    = [];
        $depth   = 0;
        $current = '';
        $inStr   = false;
        $strChar = '';

        for ($i = 0, $len = strlen($argsStr); $i < $len; $i++) {
            $ch = $argsStr[$i];

            if ($inStr) {
                $current .= $ch;
                if ($ch === $strChar && ($i === 0 || $argsStr[$i - 1] !== '\\')) {
                    $inStr = false;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inStr   = true;
                $strChar = $ch;
                $current .= $ch;
                continue;
            }

            if ($ch === '(' || $ch === '[') { $depth++; $current .= $ch; continue; }
            if ($ch === ')' || $ch === ']') { $depth--; $current .= $ch; continue; }

            if ($ch === ',' && $depth === 0) {
                $args[]  = $this->safeEval(trim($current), $data);
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

    // ────────────────────────────────────────────────────────
    // Variables
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseVariable(string $expr, array $data): array
    {
        if (!preg_match('/^\$([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/', $expr, $m)) {
            return [false, null];
        }

        $varName = $m[1];
        $rest    = $m[2] ?? '';

        if (!array_key_exists($varName, $data)) {
            return [true, null];
        }

        $value = $data[$varName];

        // Property / array access: .key or ['key'] or [index]
        while ($rest !== '') {
            $matched = false;

            if (preg_match('/^\.([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/', $rest, $pm)) {
                $key  = $pm[1];
                $rest = $pm[2];
                $matched = true;

                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } elseif (is_object($value) && isset($value->{$key})) {
                    $value = $value->{$key};
                } else {
                    return [true, null];
                }
            } elseif (preg_match('/^\[([^\]]+)\](.*)$/', $rest, $pm)) {
                $key  = trim($pm[1], "'\"");
                $rest = $pm[2];
                $matched = true;

                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } elseif (is_object($value) && isset($value->{$key})) {
                    $value = $value->{$key};
                } else {
                    return [true, null];
                }
            }

            if (!$matched) {
                // Trailing content not property access — this is not a
                // pure variable expression (e.g. "$x . 'suffix'").
                return [false, null];
            }
        }

        return [true, $value];
    }

    // ────────────────────────────────────────────────────────
    // String literals
    // ────────────────────────────────────────────────────────

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function parseStringLiteral(string $expr): array
    {
        $len = strlen($expr);
        if ($len < 2) return [false, null];

        $quote = $expr[0];
        if (!in_array($quote, ["'", '"'], true)) return [false, null];
        if ($expr[$len - 1] !== $quote) return [false, null];

        $inner = substr($expr, 1, -1);
        $inner = str_replace(['\\' . $quote, '\\\\'], [$quote, '\\'], $inner);

        return [true, $inner];
    }

    // ────────────────────────────────────────────────────────
    // Utility
    // ────────────────────────────────────────────────────────

    /**
     * Find a top-level operator (not inside parens, brackets, or strings).
     */
    private function findTopLevelOperator(string $expr, string $op, int $start = 0): int|false
    {
        $depth    = 0;
        $inStr    = false;
        $strChar  = '';
        $opLen    = strlen($op);
        $end      = strlen($expr) - $opLen + 1;

        for ($i = $start; $i < $end; $i++) {
            $ch = $expr[$i];

            if ($inStr) {
                if ($ch === $strChar && ($i === 0 || $expr[$i - 1] !== '\\')) {
                    $inStr = false;
                }
                continue;
            }

            if ($ch === "'" || $ch === '"') {
                $inStr   = true;
                $strChar = $ch;
                continue;
            }

            if ($ch === '(' || $ch === '[') { $depth++; continue; }
            if ($ch === ')' || $ch === ']') { $depth--; continue; }

            if ($depth === 0 && substr($expr, $i, $opLen) === $op) {
                return $i;
            }
        }

        return false;
    }
}
