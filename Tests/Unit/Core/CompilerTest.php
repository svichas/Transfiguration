<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Transfiguration\Core\Compiler;
use Transfiguration\Core\Hooks;
use Transfiguration\Core\ASTNodeList;
use Transfiguration\Core\TextNode;
use Transfiguration\Core\EchoNode;
use Transfiguration\Core\VarNode;
use Transfiguration\Core\IfNode;
use Transfiguration\Core\IfBranch;
use Transfiguration\Core\ForNode;

/**
 * Tests that the Compiler correctly walks the AST and produces HTML.
 */
class CompilerTest extends TestCase
{
    private function compile(ASTNodeList $ast, array $data = [], ?Hooks $hooks = null): string
    {
        return (new Compiler($data, $hooks ?? new Hooks([])))->compile($ast);
    }

    // ── TextNode ───────────────────────────────────────────

    public function testTextNode(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new TextNode('hello'));

        $this->assertSame('hello', $this->compile($ast));
    }

    public function testMultipleTextNodes(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new TextNode('hello'));
        $ast->add(new TextNode(' world'));

        $this->assertSame('hello world', $this->compile($ast));
    }

    // ── EchoNode ───────────────────────────────────────────

    public function testEchoNode(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new EchoNode('$name'));

        $this->assertSame('Alice', $this->compile($ast, ['name' => 'Alice']));
    }

    public function testEchoWithConcatenation(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new EchoNode('"Hello, " . $name'));

        $this->assertSame('Hello, Bob', $this->compile($ast, ['name' => 'Bob']));
    }

    // ── VarNode ────────────────────────────────────────────

    public function testVarNodeSetsVariable(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new VarNode('$greeting', '"Hi"'));
        $ast->add(new EchoNode('$greeting'));

        $this->assertSame('Hi', $this->compile($ast));
    }

    // ── IfNode ─────────────────────────────────────────────

    public function testIfTrue(): void
    {
        $ast = new ASTNodeList();
        $body = new ASTNodeList();
        $body->add(new TextNode('yes'));
        $ast->add(new IfNode([new IfBranch('$flag', $body)]));

        $this->assertSame('yes', $this->compile($ast, ['flag' => true]));
    }

    public function testIfFalse(): void
    {
        $ast = new ASTNodeList();
        $body = new ASTNodeList();
        $body->add(new TextNode('yes'));
        $ast->add(new IfNode([new IfBranch('$flag', $body)]));

        $this->assertSame('', $this->compile($ast, ['flag' => false]));
    }

    public function testIfElseTrue(): void
    {
        $ast = new ASTNodeList();
        $then = new ASTNodeList(); $then->add(new TextNode('T'));
        $els  = new ASTNodeList(); $els->add(new TextNode('F'));
        $ast->add(new IfNode([
            new IfBranch('$flag', $then),
            new IfBranch(null, $els),
        ]));

        $this->assertSame('T', $this->compile($ast, ['flag' => true]));
        $this->assertSame('F', $this->compile($ast, ['flag' => false]));
    }

    public function testIfElseIf(): void
    {
        $ast = new ASTNodeList();
        $a = new ASTNodeList(); $a->add(new TextNode('A'));
        $b = new ASTNodeList(); $b->add(new TextNode('B'));
        $c = new ASTNodeList(); $c->add(new TextNode('C'));
        $ast->add(new IfNode([
            new IfBranch('$x == 1', $a),
            new IfBranch('$x == 2', $b),
            new IfBranch(null, $c),
        ]));

        $this->assertSame('A', $this->compile($ast, ['x' => 1]));
        $this->assertSame('B', $this->compile($ast, ['x' => 2]));
        $this->assertSame('C', $this->compile($ast, ['x' => 99]));
    }

    // ── ForNode ────────────────────────────────────────────

    public function testForLoop(): void
    {
        $ast  = new ASTNodeList();
        $body = new ASTNodeList();
        $body->add(new EchoNode('$item'));
        $ast->add(new ForNode('', 'item', '$items', $body));

        $this->assertSame(
            'abc',
            $this->compile($ast, ['items' => ['a', 'b', 'c']])
        );
    }

    public function testForLoopWithKey(): void
    {
        $ast  = new ASTNodeList();
        $body = new ASTNodeList();
        $body->add(new EchoNode('$k'));
        $body->add(new EchoNode('":"'));
        $body->add(new EchoNode('$v'));
        $ast->add(new ForNode('k', 'v', '$map', $body));

        $this->assertSame(
            'x:1y:2',
            $this->compile($ast, ['map' => ['x' => 1, 'y' => 2]])
        );
    }

    public function testForLoopEmptyArray(): void
    {
        $ast  = new ASTNodeList();
        $body = new ASTNodeList();
        $body->add(new TextNode('x'));
        $ast->add(new ForNode('', 'item', '$empty', $body));

        $this->assertSame('', $this->compile($ast, ['empty' => []]));
    }

    // ── Hooks ──────────────────────────────────────────────

    public function testHook(): void
    {
        $hooks = new Hooks([
            'HEADER' => ['method' => fn(string $c) => "<h1>$c</h1>"],
        ]);

        $ast = new ASTNodeList();
        $ast->add(new \Transfiguration\Core\HookNode('HEADER', '"My Title"'));

        $this->assertSame(
            '<h1>My Title</h1>',
            $this->compile($ast, [], $hooks)
        );
    }

    // ── Complex ────────────────────────────────────────────

    public function testComplexTemplate(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new TextNode('<ul>'));
        $body = new ASTNodeList();
        $body->add(new TextNode('<li>'));
        $body->add(new EchoNode('$item'));
        $body->add(new TextNode('</li>'));
        $ast->add(new ForNode('', 'item', '$items', $body));
        $ast->add(new TextNode('</ul>'));

        $this->assertSame(
            '<ul><li>a</li><li>b</li></ul>',
            $this->compile($ast, ['items' => ['a', 'b']])
        );
    }
}
