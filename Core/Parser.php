<?php

declare(strict_types=1);

namespace Transfiguration\Core;

/**
 * Builds an Abstract Syntax Tree (AST) from the flat token array
 * produced by the Lexer.
 *
 * The AST captures the hierarchical structure of the template —
 * control-flow blocks become subtrees — so the Compiler can walk
 * the tree once and produce the final HTML.
 */
class Parser
{
    /** @var array<int, array{type: string, content: string, tab: int}> */
    private array $tokens;

    /** @var array<string, true> Token types that have registered hooks. */
    private array $hookTypes;

    /**
     * @param array<int, array> $tokens Flat token array from Lexer.
     * @param Hooks|null        $hooks  Hook registry (may be empty).
     */
    public function __construct(array $tokens, ?Hooks $hooks = null)
    {
        $this->tokens = $tokens;
        $this->hookTypes = [];

        if ($hooks !== null) {
            foreach (array_keys($hooks->getHooks()) as $type) {
                $this->hookTypes[$type] = true;
            }
        }
    }

    // ────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────

    /**
     * Parse the entire token stream and return the root AST node list.
     */
    public function parse(): ASTNodeList
    {
        $pos = 0;
        return $this->parseNodeList($pos);
    }

    // ────────────────────────────────────────────────────────
    // Recursive descent
    // ────────────────────────────────────────────────────────

    /**
     * Parse a sequence of nodes starting at $pos until tokens are
     * exhausted.  $pos is advanced past consumed tokens.
     */
    private function parseNodeList(int &$pos): ASTNodeList
    {
        $list = new ASTNodeList();
        $len  = count($this->tokens);

        while ($pos < $len) {
            $token = $this->tokens[$pos];
            $type  = strtoupper((string) $token['type']);

            // Delegate to the appropriate sub-parser.
            $node = match ($type) {
                'HTML'             => $this->parseText($token),
                'ECHO', 'PRINT'    => $this->parseEcho($token),
                'VAR'              => $this->parseVar($token),
                'IF'               => $this->parseIf($pos),
                'FOR'              => $this->parseFor($pos),
                'INCLUDE','REQUIRE'=> $this->parseInclude($token),
                default            => $this->parseHookOrSkip($token),
            };

            if ($node !== null) {
                $list->add($node);
            }

            $pos++;
        }

        return $list;
    }

    // ────────────────────────────────────────────────────────
    // Leaf parsers (consume exactly one token)
    // ────────────────────────────────────────────────────────

    private function parseText(array $token): TextNode
    {
        return new TextNode((string) $token['content']);
    }

    private function parseEcho(array $token): EchoNode
    {
        return new EchoNode((string) $token['content']);
    }

    private function parseVar(array $token): ?VarNode
    {
        $content = (string) $token['content'];
        $eqPos   = strpos($content, '=');

        if ($eqPos === false) {
            return null; // malformed — skip
        }

        return new VarNode(
            trim(substr($content, 0, $eqPos)),
            trim(substr($content, $eqPos + 1))
        );
    }

    private function parseInclude(array $token): IncludeNode
    {
        return new IncludeNode((string) $token['content']);
    }

    private function parseHookOrSkip(array $token): ?HookNode
    {
        $type = strtoupper((string) $token['type']);

        if (isset($this->hookTypes[$type])) {
            return new HookNode($type, (string) $token['content']);
        }

        return null;
    }

    // ────────────────────────────────────────────────────────
    // Block parsers (consume multiple tokens)
    // ────────────────────────────────────────────────────────

    /**
     * Parse IF … ELSEIF … ELSE … ENDIF into an IfNode.
     *
     * Entry: $pos points at the IF token.
     * Exit:  $pos points at the ENDIF token (caller will ++ past it).
     */
    private function parseIf(int &$pos): IfNode
    {
        $tab = (int) $this->tokens[$pos]['tab'];
        $pos++; // consume IF

        $branches = [];

        // First branch: IF body
        $condition = (string) $this->tokens[$pos - 1]['content'];
        $branches[] = new IfBranch($condition, $this->parseBranchBody($pos, $tab));

        // Subsequent branches: ELSEIF / ELSE
        while ($pos < count($this->tokens)) {
            $type = strtoupper((string) $this->tokens[$pos]['type']);
            $tokTab = (int) $this->tokens[$pos]['tab'];

            if ($tokTab !== $tab) {
                // Nested content — skip (handled by parseBranchBody).
                // This shouldn't happen; safety break.
                break;
            }

            if ($type === 'ENDIF') {
                break;
            }

            if ($type === 'ELSEIF') {
                $condition = (string) $this->tokens[$pos]['content'];
                $pos++; // consume ELSEIF
                $branches[] = new IfBranch($condition, $this->parseBranchBody($pos, $tab));
            } elseif ($type === 'ELSE') {
                $pos++; // consume ELSE
                $branches[] = new IfBranch(null, $this->parseBranchBody($pos, $tab));
            } else {
                break; // safety
            }
        }

        // $pos now at ENDIF
        return new IfNode($branches);
    }

    /**
     * Parse the body of a single IF/ELSEIF/ELSE branch.
     *
     * Collects all tokens until we hit ELSEIF, ELSE, or ENDIF
     * at the same tab level.
     *
     * Exit: $pos is left pointing at the delimiter token.
     */
    private function parseBranchBody(int &$pos, int $tab): ASTNodeList
    {
        $list = new ASTNodeList();
        $len  = count($this->tokens);
        $stopTypes = ['ELSEIF' => true, 'ELSE' => true, 'ENDIF' => true];

        while ($pos < $len) {
            $token = $this->tokens[$pos];
            $type  = strtoupper((string) $token['type']);
            $tokTab = (int) $token['tab'];

            // Stop at a branch delimiter at the same nesting level.
            if ($tokTab === $tab && isset($stopTypes[$type])) {
                break;
            }

            // Recurse into nested blocks.
            $node = match ($type) {
                'HTML'             => $this->parseText($token),
                'ECHO', 'PRINT'    => $this->parseEcho($token),
                'VAR'              => $this->parseVar($token),
                'IF'               => $this->parseIf($pos),
                'FOR'              => $this->parseFor($pos),
                'INCLUDE','REQUIRE'=> $this->parseInclude($token),
                default            => $this->parseHookOrSkip($token),
            };

            if ($node !== null) {
                $list->add($node);
            }

            $pos++;
        }

        return $list;
    }

    /**
     * Parse FOR … ENDFOR into a ForNode.
     *
     * Entry: $pos points at the FOR token.
     * Exit:  $pos points at the ENDFOR token.
     */
    private function parseFor(int &$pos): ForNode
    {
        $tab = (int) $this->tokens[$pos]['tab'];
        $content = (string) $this->tokens[$pos]['content'];
        $pos++; // consume FOR

        // Parse "key : value in array" syntax.
        $contentParts = preg_split('/\s+in\s+/i', $content, 2);
        $forExpr = trim($contentParts[0]);
        $arrayExpr = isset($contentParts[1]) ? trim($contentParts[1]) : '';

        $keyVar = '';
        $valueVar = $forExpr;

        if (strpos($forExpr, ':') !== false) {
            [$keyVar, $valueVar] = explode(':', $forExpr, 2);
            $keyVar   = trim($keyVar);
            $valueVar = trim($valueVar);
        }

        // Parse body until ENDFOR at the same tab level.
        $body = $this->parseBranchBody($pos, $tab);

        // $pos is now at ENDFOR
        return new ForNode($keyVar, $valueVar, $arrayExpr, $body);
    }
}

