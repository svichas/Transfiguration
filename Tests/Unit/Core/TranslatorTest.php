<?php

use PHPUnit\Framework\TestCase;

use Transfiguration\Core\Compiler;
use Transfiguration\Core\Hooks;
use Transfiguration\Core\ASTNodeList;
use Transfiguration\Core\TextNode;

class TranslatorTest extends TestCase
{
    /**
     * Test that the Compiler correctly concatenates HTML text nodes.
     */
    public function testCompilerReturnsCorrectOutputFromAST(): void
    {
        $ast = new ASTNodeList();
        $ast->add(new TextNode('hello, world!'));

        $compiler = new Compiler([], new Hooks([]));

        $this->assertEquals('hello, world!', $compiler->compile($ast));
    }
}