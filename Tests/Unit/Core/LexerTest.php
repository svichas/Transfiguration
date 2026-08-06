<?php

use PHPUnit\Framework\TestCase;

use Transfiguration\Core\Lexer;

class LexerTest extends TestCase
{
    /**
     * Test that the Lexer correctly identifies token types from code blocks.
     */
    public function testLexerProducesCorrectTokenTypes(): void
    {
        $lexer  = new Lexer('before {{ echo "hello" }} after {{ if $x }}inner{{ endif }} end');
        $tokens = $lexer->exportTokens();

        $this->assertCount(6, $tokens);

        $this->assertEquals('HTML',   $tokens[0]['type']);
        $this->assertEquals('before ', $tokens[0]['content']);

        $this->assertEquals('ECHO',    $tokens[1]['type']);
        $this->assertEquals('"hello"', $tokens[1]['content']);

        $this->assertEquals('HTML',    $tokens[2]['type']);
        $this->assertEquals(' after ', $tokens[2]['content']);

        $this->assertEquals('IF',  $tokens[3]['type']);
        $this->assertEquals('$x',  $tokens[3]['content']);

        $this->assertEquals('HTML',   $tokens[4]['type']);
        $this->assertEquals('inner',  $tokens[4]['content']);

        $this->assertEquals('ENDIF', $tokens[5]['type']);
    }

    /**
     * Test that comments are fully stripped from the token stream.
     */
    public function testLexerStripsComments(): void
    {
        $lexer  = new Lexer('visible {# hidden comment #} visible');
        $tokens = $lexer->exportTokens();

        $this->assertCount(2, $tokens);
        $this->assertEquals('HTML', $tokens[0]['type']);
        $this->assertEquals('HTML', $tokens[1]['type']);
        $this->assertEquals('visible ', $tokens[0]['content']);
        $this->assertEquals(' visible', $tokens[1]['content']);
    }

    /**
     * Test that empty code blocks {{ }} produce no token.
     */
    public function testLexerSkipsEmptyCodeBlock(): void
    {
        $lexer  = new Lexer('before {{ }} after');
        $tokens = $lexer->exportTokens();

        // Only two HTML tokens — the empty block is skipped.
        $this->assertCount(2, $tokens);
        $this->assertEquals('before ', $tokens[0]['content']);
        $this->assertEquals(' after', $tokens[1]['content']);
    }

    /**
     * Test that tab levels are correctly assigned for nested blocks.
     */
    public function testLexerAssignsCorrectTabLevels(): void
    {
        $lexer  = new Lexer('{{ if $a }}{{ for $x in $y }}{{ echo $x }}{{ endfor }}{{ endif }}');
        $tokens = $lexer->exportTokens();

        // IF at tab 0
        $this->assertEquals(0, $tokens[0]['tab']);
        $this->assertEquals('IF', $tokens[0]['type']);

        // FOR at tab 1 (nested inside IF)
        $this->assertEquals(1, $tokens[1]['tab']);
        $this->assertEquals('FOR', $tokens[1]['type']);

        // ECHO at tab 2 (nested inside FOR)
        $this->assertEquals(2, $tokens[2]['tab']);
        $this->assertEquals('ECHO', $tokens[2]['type']);

        // ENDFOR at tab 1
        $this->assertEquals(1, $tokens[3]['tab']);
        $this->assertEquals('ENDFOR', $tokens[3]['type']);

        // ENDIF at tab 0
        $this->assertEquals(0, $tokens[4]['tab']);
        $this->assertEquals('ENDIF', $tokens[4]['type']);
    }
}