<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Transfiguration\Helpers\ExpressionParser;

class ExpressionParserTest extends TestCase
{
    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ExpressionParser();
    }

    // ── String literals ────────────────────────────────────

    public function testSingleQuotedString(): void
    {
        $this->assertSame('hello', $this->parser->evaluate("'hello'", []));
    }

    public function testDoubleQuotedString(): void
    {
        $this->assertSame('world', $this->parser->evaluate('"world"', []));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', $this->parser->evaluate('', []));
    }

    // ── Numeric literals ───────────────────────────────────

    public function testIntegerLiteral(): void
    {
        $this->assertSame(42, $this->parser->evaluate('42', []));
    }

    public function testFloatLiteral(): void
    {
        $this->assertSame(3.14, $this->parser->evaluate('3.14', []));
    }

    public function testNegativeInteger(): void
    {
        $this->assertSame(-5, $this->parser->evaluate('-5', []));
    }

    // ── Boolean / null literals ────────────────────────────

    public function testTrueLiteral(): void
    {
        $this->assertTrue($this->parser->evaluate('true', []));
    }

    public function testFalseLiteral(): void
    {
        $this->assertFalse($this->parser->evaluate('false', []));
    }

    public function testNullLiteral(): void
    {
        $this->assertNull($this->parser->evaluate('null', []));
    }

    // ── Variables ──────────────────────────────────────────

    public function testSimpleVariable(): void
    {
        $this->assertSame('Alice', $this->parser->evaluate('$name', ['name' => 'Alice']));
    }

    public function testUndefinedVariableReturnsNull(): void
    {
        $this->assertNull($this->parser->evaluate('$missing', []));
    }

    public function testVariableDotAccess(): void
    {
        $this->assertSame('Bob', $this->parser->evaluate(
            '$user.name',
            ['user' => ['name' => 'Bob']]
        ));
    }

    public function testVariableBracketAccess(): void
    {
        $this->assertSame('x', $this->parser->evaluate(
            '$arr["key"]',
            ['arr' => ['key' => 'x']]
        ));
    }

    public function testVariableNumericIndex(): void
    {
        $this->assertSame('b', $this->parser->evaluate(
            '$arr[1]',
            ['arr' => ['a', 'b', 'c']]
        ));
    }

    // ── Concatenation ──────────────────────────────────────

    public function testConcatenation(): void
    {
        $this->assertSame('Hello World', $this->parser->evaluate(
            '"Hello" . " " . "World"',
            []
        ));
    }

    public function testConcatenationWithVariable(): void
    {
        $this->assertSame('Hi Alice', $this->parser->evaluate(
            '"Hi " . $name',
            ['name' => 'Alice']
        ));
    }

    // ── Arithmetic ─────────────────────────────────────────

    public function testAddition(): void
    {
        $this->assertSame(7, $this->parser->evaluate('3 + 4', []));
    }

    public function testSubtraction(): void
    {
        $this->assertSame(1, $this->parser->evaluate('5 - 4', []));
    }

    public function testMultiplication(): void
    {
        $this->assertSame(12, $this->parser->evaluate('3 * 4', []));
    }

    public function testDivision(): void
    {
        $this->assertSame(2.5, $this->parser->evaluate('5 / 2', []));
    }

    public function testDivisionByZeroReturnsZero(): void
    {
        $this->assertSame(0, $this->parser->evaluate('5 / 0', []));
    }

    public function testModulo(): void
    {
        $this->assertSame(1, $this->parser->evaluate('7 % 3', []));
    }

    // ── Comparisons ────────────────────────────────────────

    public function testEqual(): void
    {
        $this->assertTrue($this->parser->evaluate('5 == 5', []));
    }

    public function testNotEqual(): void
    {
        $this->assertTrue($this->parser->evaluate('5 != 3', []));
    }

    public function testIdentical(): void
    {
        $this->assertTrue($this->parser->evaluate('5 === 5', []));
    }

    public function testNotIdentical(): void
    {
        $this->assertTrue($this->parser->evaluate("'5' !== 5", []));
    }

    public function testLessThan(): void
    {
        $this->assertTrue($this->parser->evaluate('3 < 5', []));
    }

    public function testGreaterThan(): void
    {
        $this->assertTrue($this->parser->evaluate('5 > 3', []));
    }

    public function testLessThanOrEqual(): void
    {
        $this->assertTrue($this->parser->evaluate('5 <= 5', []));
    }

    public function testGreaterThanOrEqual(): void
    {
        $this->assertTrue($this->parser->evaluate('5 >= 3', []));
    }

    // ── Logical operators ──────────────────────────────────

    public function testLogicalAndTrue(): void
    {
        $this->assertTrue($this->parser->evaluate('true && true', []));
    }

    public function testLogicalAndFalse(): void
    {
        $this->assertFalse($this->parser->evaluate('true && false', []));
    }

    public function testLogicalOr(): void
    {
        $this->assertTrue($this->parser->evaluate('false || true', []));
    }

    public function testLogicalOrShortCircuit(): void
    {
        $this->assertSame('left', $this->parser->evaluate(
            '"left" || "right"',
            []
        ));
    }

    public function testLogicalAndShortCircuitFalse(): void
    {
        $this->assertSame('', $this->parser->evaluate(
            '"" && "right"',
            []
        ));
    }

    // ── Unary not ──────────────────────────────────────────

    public function testUnaryNotTrue(): void
    {
        $this->assertFalse($this->parser->evaluate('!true', []));
    }

    public function testUnaryNotFalse(): void
    {
        $this->assertTrue($this->parser->evaluate('!false', []));
    }

    // ── Ternary ────────────────────────────────────────────

    public function testTernaryTrue(): void
    {
        $this->assertSame('yes', $this->parser->evaluate('true ? "yes" : "no"', []));
    }

    public function testTernaryFalse(): void
    {
        $this->assertSame('no', $this->parser->evaluate('false ? "yes" : "no"', []));
    }

    // ── Function calls ─────────────────────────────────────

    public function testStrlen(): void
    {
        $this->assertSame(5, $this->parser->evaluate('strlen("hello")', []));
    }

    public function testStrtoupper(): void
    {
        $this->assertSame('HELLO', $this->parser->evaluate('strtoupper("hello")', []));
    }

    public function testCount(): void
    {
        $this->assertSame(3, $this->parser->evaluate('count($arr)', ['arr' => [1, 2, 3]]));
    }

    public function testTrim(): void
    {
        $this->assertSame('x', $this->parser->evaluate('trim("  x  ")', []));
    }

    public function testUnsafeFunctionRejected(): void
    {
        // 'system' is not in the whitelist — should return the raw string.
        $this->assertSame('system("ls")', $this->parser->evaluate('system("ls")', []));
    }

    // ── Parenthesized expressions ──────────────────────────

    public function testParentheses(): void
    {
        $this->assertSame(21, $this->parser->evaluate('(2 + 5) * 3', []));
    }

    // ── Semicolon truncation ───────────────────────────────

    public function testSemicolonTruncation(): void
    {
        // Only the first statement is evaluated.
        $this->assertSame(1, $this->parser->evaluate('1; dangerous_code', []));
    }

    // ── Edge cases ─────────────────────────────────────────

    public function testWhitespaceAroundExpression(): void
    {
        $this->assertSame('ok', $this->parser->evaluate('  "ok"  ', []));
    }

    public function testComplexExpression(): void
    {
        $this->assertSame('Hello, Alice!', $this->parser->evaluate(
            '"Hello, " . $name . "!"',
            ['name' => 'Alice']
        ));
    }
}
