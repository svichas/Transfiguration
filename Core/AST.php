<?php

declare(strict_types=1);

namespace Transfiguration\Core;

/**
 * Base class for all AST nodes in the template tree.
 *
 * Each node knows how to compile itself given a Compiler context.
 */
abstract class ASTNode
{
    /**
     * Compile this node and return its HTML output.
     */
    abstract public function compile(Compiler $compiler): string;
}

// ────────────────────────────────────────────────────────────
// Container
// ────────────────────────────────────────────────────────────

/**
 * An ordered list of AST nodes (a block / root).
 */
class ASTNodeList
{
    /** @var ASTNode[] */
    public array $children = [];

    public function add(ASTNode $node): void
    {
        $this->children[] = $node;
    }
}

// ────────────────────────────────────────────────────────────
// Leaf nodes
// ────────────────────────────────────────────────────────────

/**
 * Raw HTML text that passes through unchanged.
 */
class TextNode extends ASTNode
{
    public function __construct(public string $content) {}

    public function compile(Compiler $compiler): string
    {
        return $this->content;
    }
}

/**
 * {{ echo $expr }} / {{ print $expr }} — evaluate and output.
 */
class EchoNode extends ASTNode
{
    public function __construct(public string $expression) {}

    public function compile(Compiler $compiler): string
    {
        return (string) $compiler->evaluate($this->expression);
    }
}

/**
 * {{ var $name = $expr }} — assign a variable (no output).
 */
class VarNode extends ASTNode
{
    public function __construct(
        public string $name,
        public string $valueExpression
    ) {}

    public function compile(Compiler $compiler): string
    {
        $compiler->setVar($this->name, $this->valueExpression);
        return '';
    }
}

/**
 * {{ include 'path' }} / {{ require 'path' }} — embed another template.
 */
class IncludeNode extends ASTNode
{
    public function __construct(public string $pathExpression) {}

    public function compile(Compiler $compiler): string
    {
        return $compiler->includeTemplate($this->pathExpression);
    }
}

/**
 * Unknown token types that match a registered hook.
 */
class HookNode extends ASTNode
{
    public function __construct(
        public string $hookType,
        public string $content
    ) {}

    public function compile(Compiler $compiler): string
    {
        return $compiler->runHook($this->hookType, $this->content);
    }
}

// ────────────────────────────────────────────────────────────
// Control-flow nodes
// ────────────────────────────────────────────────────────────

/**
 * A single branch of an IF / ELSEIF / ELSE chain.
 */
class IfBranch
{
    /**
     * @param string|null $condition Expression for IF/ELSEIF, or null for ELSE.
     * @param ASTNodeList $body      Tokens inside this branch.
     */
    public function __construct(
        public ?string $condition,
        public ASTNodeList $body
    ) {}
}

/**
 * {{ if … }} … {{ elseif … }} … {{ else }} … {{ endif }}
 */
class IfNode extends ASTNode
{
    /** @param IfBranch[] $branches */
    public function __construct(public array $branches) {}

    public function compile(Compiler $compiler): string
    {
        foreach ($this->branches as $branch) {
            if ($branch->condition === null || $compiler->evaluate($branch->condition)) {
                return $compiler->compileNodeList($branch->body);
            }
        }
        return '';
    }
}

/**
 * {{ for $key : $val in $array }} … {{ endfor }}
 */
class ForNode extends ASTNode
{
    public function __construct(
        public string $keyVar,       // '' when no key
        public string $valueVar,
        public string $arrayExpression,
        public ASTNodeList $body
    ) {}

    public function compile(Compiler $compiler): string
    {
        $array = $compiler->evaluate($this->arrayExpression);

        if (!is_iterable($array)) {
            return '';
        }

        $output = '';

        foreach ($array as $key => $value) {
            $scope = [$this->valueVar => $value];
            if ($this->keyVar !== '') {
                $scope[$this->keyVar] = $key;
            }

            $compiler->pushScope($scope);
            $output .= $compiler->compileNodeList($this->body);
            $compiler->popScope();
        }

        return $output;
    }
}
