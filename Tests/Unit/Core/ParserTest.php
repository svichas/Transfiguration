<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Transfiguration\Core\Lexer;
use Transfiguration\Core\Parser;
use Transfiguration\Core\Hooks;
use Transfiguration\Core\ASTNodeList;
use Transfiguration\Core\TextNode;
use Transfiguration\Core\EchoNode;
use Transfiguration\Core\VarNode;
use Transfiguration\Core\IfNode;
use Transfiguration\Core\ForNode;
use Transfiguration\Core\IncludeNode;

/**
 * Tests that the Parser correctly builds an AST from flat tokens.
 */
class ParserTest extends TestCase
{
    private function parse(string $template): ASTNodeList
    {
        $lexer  = new Lexer($template);
        $parser = new Parser($lexer->exportTokens());
        return $parser->parse();
    }

    // ── Plain HTML ─────────────────────────────────────────

    public function testPlainHtmlProducesSingleTextNode(): void
    {
        $ast = $this->parse('hello world');

        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertSame('hello world', $ast->children[0]->content);
    }

    // ── Echo ───────────────────────────────────────────────

    public function testEchoProducesEchoNode(): void
    {
        $ast = $this->parse('{{ echo $name }}');

        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(EchoNode::class, $ast->children[0]);
        $this->assertSame('$name', $ast->children[0]->expression);
    }

    public function testPrintProducesEchoNode(): void
    {
        $ast = $this->parse('{{ print $x }}');

        $this->assertInstanceOf(EchoNode::class, $ast->children[0]);
        $this->assertSame('$x', $ast->children[0]->expression);
    }

    // ── Var ────────────────────────────────────────────────

    public function testVarProducesVarNode(): void
    {
        $ast = $this->parse('{{ var $x = "hello" }}');

        $this->assertInstanceOf(VarNode::class, $ast->children[0]);
        $this->assertSame('$x', $ast->children[0]->name);
        $this->assertSame('"hello"', $ast->children[0]->valueExpression);
    }

    // ── Include ────────────────────────────────────────────

    public function testIncludeProducesIncludeNode(): void
    {
        $ast = $this->parse("{{ include 'header.html' }}");

        $this->assertInstanceOf(IncludeNode::class, $ast->children[0]);
    }

    // ── If / ElseIf / Else ─────────────────────────────────

    public function testIfWithoutElse(): void
    {
        $ast = $this->parse('{{ if $x }}yes{{ endif }}');

        $this->assertInstanceOf(IfNode::class, $ast->children[0]);
        /** @var IfNode $if */
        $if = $ast->children[0];
        $this->assertCount(1, $if->branches);
        $this->assertSame('$x', $if->branches[0]->condition);
        $this->assertCount(1, $if->branches[0]->body->children);
        $this->assertInstanceOf(TextNode::class, $if->branches[0]->body->children[0]);
        $this->assertSame('yes', $if->branches[0]->body->children[0]->content);
    }

    public function testIfElse(): void
    {
        $ast = $this->parse('{{ if $x }}yes{{ else }}no{{ endif }}');

        /** @var IfNode $if */
        $if = $ast->children[0];
        $this->assertCount(2, $if->branches);
        $this->assertSame('$x', $if->branches[0]->condition);
        $this->assertNull($if->branches[1]->condition);
    }

    public function testIfElseIfElse(): void
    {
        $ast = $this->parse('{{ if $a }}A{{ elseif $b }}B{{ else }}C{{ endif }}');

        /** @var IfNode $if */
        $if = $ast->children[0];
        $this->assertCount(3, $if->branches);
        $this->assertSame('$a', $if->branches[0]->condition);
        $this->assertSame('$b', $if->branches[1]->condition);
        $this->assertNull($if->branches[2]->condition);
    }

    public function testNestedIf(): void
    {
        $ast = $this->parse('{{ if $a }}{{ if $b }}inner{{ endif }}{{ endif }}');

        /** @var IfNode $outer */
        $outer = $ast->children[0];
        $this->assertCount(1, $outer->branches);
        /** @var IfNode $inner */
        $inner = $outer->branches[0]->body->children[0];
        $this->assertInstanceOf(IfNode::class, $inner);
        $this->assertSame('$b', $inner->branches[0]->condition);
    }

    // ── For ────────────────────────────────────────────────

    public function testForLoop(): void
    {
        $ast = $this->parse('{{ for $item in $items }}{{ echo $item }}{{ endfor }}');

        /** @var ForNode $for */
        $for = $ast->children[0];
        $this->assertInstanceOf(ForNode::class, $for);
        $this->assertSame('', $for->keyVar);
        $this->assertSame('item', $for->valueVar);
        $this->assertSame('$items', $for->arrayExpression);
        $this->assertCount(1, $for->body->children);
        $this->assertInstanceOf(EchoNode::class, $for->body->children[0]);
    }

    public function testForLoopWithKey(): void
    {
        $ast = $this->parse('{{ for $k : $v in $map }}{{ echo $k }}{{ endfor }}');

        /** @var ForNode $for */
        $for = $ast->children[0];
        $this->assertSame('k', $for->keyVar);
        $this->assertSame('v', $for->valueVar);
        $this->assertSame('$map', $for->arrayExpression);
    }

    public function testNestedFor(): void
    {
        $ast = $this->parse(
            '{{ for $a in $outer }}{{ for $b in $inner }}x{{ endfor }}{{ endfor }}'
        );

        /** @var ForNode $outer */
        $outer = $ast->children[0];
        $this->assertCount(1, $outer->body->children);
        /** @var ForNode $inner */
        $inner = $outer->body->children[0];
        $this->assertInstanceOf(ForNode::class, $inner);
    }

    // ── Mixed ──────────────────────────────────────────────

    public function testMixedHtmlAndCode(): void
    {
        $ast = $this->parse('before {{ echo $x }} after');

        $this->assertCount(3, $ast->children);
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        $this->assertInstanceOf(EchoNode::class, $ast->children[1]);
        $this->assertInstanceOf(TextNode::class, $ast->children[2]);
        $this->assertSame('before ', $ast->children[0]->content);
        $this->assertSame(' after', $ast->children[2]->content);
    }

    // ── Hooks ──────────────────────────────────────────────

    public function testHookProducesHookNode(): void
    {
        $lexer  = new Lexer("{{ header 'title' }}");
        $hooks  = new Hooks(['HEADER' => ['method' => fn(string $c) => "<h1>$c</h1>"]]);
        $parser = new Parser($lexer->exportTokens(), $hooks);
        $ast    = $parser->parse();

        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(\Transfiguration\Core\HookNode::class, $ast->children[0]);
    }

    // ── Comments ───────────────────────────────────────────

    public function testCommentsAreStripped(): void
    {
        $ast = $this->parse('before {# hidden #} after');

        $this->assertCount(1, $ast->children);
        $this->assertInstanceOf(TextNode::class, $ast->children[0]);
        // Comment removed; double-space remains.
        $this->assertSame('before  after', $ast->children[0]->content);
    }
}
