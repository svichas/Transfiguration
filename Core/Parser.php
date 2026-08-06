<?php

declare(strict_types=1);

namespace Transfiguration\Core;

use Transfiguration\Core\Hooks;
use Transfiguration\Helpers\Evaluator;
use Transfiguration\Transfiguration;

/**
 * Class for translating code tokens to HTML tokens.
 */
class Parser
{
    /** Maximum iterations to prevent infinite loops. */
    private const MAX_ITERATIONS = 50000;

    /** @var array<int, array> Original tokens. */
    private $toks = [];

    /** @var array<int, array> Working token list. */
    private $tokens = [];

    /** @var Evaluator */
    private $evaluator;

    /** @var Hooks */
    private $hooks;

    /** @var array<string, string> Block-type to end-keyword mapping. */
    private $blockEnds = [
        'FOR'   => 'ENDFOR',
        'WHILE' => 'ENDWHILE',
        'IF'    => 'ENDIF',
    ];

    /** @var string Base path for include/require resolution. */
    private $baseIncludePath = '';

    /**
     * @param array<int, array> $tokens
     * @param array<string, mixed> $data
     * @param string $path Base path for include/require.
     * @param array<string, array> $hooks
     */
    public function __construct(array $tokens = [], array $data = [], string $path = '', array $hooks = [])
    {
        $this->toks = $tokens;
        $this->tokens = $tokens;
        $this->evaluator = new Evaluator($data);
        $this->baseIncludePath = $this->normalizePath($path);
        $this->hooks = new Hooks($hooks);
        $this->parseTokens();
    }

    /**
     * Translate code tokens to HTML tokens.
     */
    public function parseTokens(): void
    {
        $i = 0;
        $iterations = 0;
        $tokenCount = count($this->tokens);

        while ($i < $tokenCount && $iterations < self::MAX_ITERATIONS) {
            $iterations++;

            if (!isset($this->tokens[$i])) {
                $i++;
                continue;
            }

            $token = $this->tokens[$i];
            $tokenType = strtoupper((string)($token['type'] ?? ''));
            $tokenData = isset($token['data']) && is_array($token['data']) ? $token['data'] : [];

            switch ($tokenType) {
                case 'INCLUDE':
                case 'REQUIRE':
                    $this->includeTemplate($token, $i);
                    break;

                case 'ECHO':
                case 'PRINT':
                    $this->setToken($i, $this->evaluator->evaluate((string)$token['content'], $tokenData));
                    break;

                case 'VAR':
                    $this->handleVarToken($token, $i, $tokenData);
                    break;

                case 'FOR':
                    $returnFors = $this->handleForToken($token, $i);
                    $this->appendTokens($i, $returnFors);
                    // Recompile from start after token injection.
                    $i = 0;
                    $tokenCount = count($this->tokens);
                    continue 2;

                case 'IF':
                    $this->handleIfBlock($token, $i, $tokenData);
                    break;

                default:
                    // Check hooks for non-HTML tokens.
                    if ($tokenType !== 'HTML') {
                        $evaluatedContent = $this->evaluator->evaluate(
                            (string)($token['content'] ?? ''),
                            $tokenData
                        );
                        $result = $this->hooks->checkHooks($token, (string)$evaluatedContent);
                        if ($result !== false) {
                            $this->setToken($i, $result);
                        }
                    }
                    break;
            }

            $i++;
        }
    }

    /**
     * Handle VAR token: parse variable assignment and store it.
     */
    private function handleVarToken(array $token, int $index, array $tokenData): void
    {
        $content = (string)($token['content'] ?? '');
        $eqPos = strpos($content, '=');

        if ($eqPos === false) {
            $this->removeToken($index);
            return;
        }

        $varName = trim(substr($content, 0, $eqPos));
        $varContent = trim(substr($content, $eqPos + 1));

        $this->evaluator->setVar($varName, $varContent, $tokenData);
        $this->removeToken($index);
    }

    /**
     * Handle FOR token: extract loop body and produce expanded tokens.
     *
     * @return array<int, array>
     */
    private function handleForToken(array $token, int $index): array
    {
        $forTokens = $this->findEnd($token, $index);

        $contentParts = preg_split('/\s+in\s+/i', (string)$token['content'], 2);
        if (count($contentParts) < 2) {
            return [];
        }

        $forVarPart = trim($contentParts[0]);
        $forArrayExpr = trim($contentParts[1]);

        $forKey = '';
        $forVar = $forVarPart;
        if (strpos($forVarPart, ':') !== false) {
            [$forKey, $forVar] = explode(':', $forVarPart, 2);
            $forKey = trim($forKey);
            $forVar = trim($forVar);
        }

        return $this->evaluator->doFor($forTokens, $forArrayExpr, $forVar, $forKey);
    }

    /**
     * Handle IF/ELSEIF/ELSE/ENDIF block resolution.
     */
    private function handleIfBlock(array $token, int $startIndex, array $tokenData): void
    {
        $tab = (int)($token['tab'] ?? 0);

        // Phase 1: find which branch is executable.
        $executablePosition = $this->findExecutableBranch($startIndex, $tab, $tokenData);

        // Phase 2: remove all non-executed tokens in this block.
        $this->cleanIfBlock($startIndex, $tab, $executablePosition);
    }

    /**
     * Find the position of the first true IF/ELSEIF or the ELSE branch.
     */
    private function findExecutableBranch(int $startIndex, int $tab, array $tokenData): int
    {
        $step = $startIndex;
        $iterations = 0;

        while (isset($this->tokens[$step]) && $iterations < self::MAX_ITERATIONS) {
            $iterations++;
            $ifToken = $this->tokens[$step];

            if (((int)($ifToken['tab'] ?? -1)) !== $tab) {
                $step++;
                continue;
            }

            $type = strtoupper((string)($ifToken['type'] ?? ''));

            switch ($type) {
                case 'IF':
                case 'ELSEIF':
                    if ($this->evaluator->evaluate((string)$ifToken['content'], $tokenData)) {
                        return $step;
                    }
                    break;
                case 'ELSE':
                    return $step;
                case 'ENDIF':
                    return $startIndex; // fallback: show nothing
            }

            $step++;
        }

        return $startIndex;
    }

    /**
     * Remove all tokens in the IF block except the executable branch content.
     */
    private function cleanIfBlock(int $startIndex, int $tab, int $executablePosition): void
    {
        $step = $startIndex;
        $show = false;
        $iterations = 0;
        $ended = false;

        while (isset($this->tokens[$step]) && !$ended && $iterations < self::MAX_ITERATIONS) {
            $iterations++;
            $ifToken = $this->tokens[$step];

            if (((int)($ifToken['tab'] ?? -1)) === $tab) {
                $type = strtoupper((string)($ifToken['type'] ?? ''));

                switch ($type) {
                    case 'IF':
                    case 'ELSEIF':
                    case 'ELSE':
                        $show = ($step === $executablePosition);
                        $this->removeToken($step);
                        break;

                    case 'ENDIF':
                        $show = false;
                        $ended = true;
                        $this->removeToken($step);
                        break;
                }
            }

            if (!$show && !$ended) {
                $this->removeToken($step);
            }

            $step++;
        }
    }

    /**
     * Include/require another template with path traversal protection.
     */
    public function includeTemplate(array $token = [], int $index = -1): void
    {
        $tokenData = isset($token['data']) && is_array($token['data']) ? $token['data'] : [];

        $relativePath = (string)$this->evaluator->evaluate((string)$token['content'], $tokenData);

        // Resolve and validate the path to prevent directory traversal.
        $resolvedPath = $this->resolveIncludePath($relativePath);

        if ($resolvedPath === null || !file_exists($resolvedPath)) {
            return;
        }

        $content = file_get_contents($resolvedPath);
        if ($content === false) {
            return;
        }

        $tempData = array_merge($this->evaluator->getData(), $tokenData);
        $transfig = new Transfiguration($content, $tempData, $this->baseIncludePath);
        $incTokens = $transfig->parserTokens();
        $this->appendTokens($index, $incTokens);
    }

    /**
     * Resolve an include path safely, preventing directory traversal.
     *
     * @param string $relativePath The requested relative path.
     * @return string|null The resolved absolute path, or null if invalid.
     */
    private function resolveIncludePath(string $relativePath): ?string
    {
        // Remove any null bytes (poison byte attack).
        $relativePath = str_replace("\0", '', $relativePath);

        // Build the candidate path.
        $candidatePath = $this->baseIncludePath . $relativePath;

        // Resolve to absolute, canonical form.
        $realPath = realpath($candidatePath);

        if ($realPath === false) {
            return null;
        }

        // Ensure the resolved path is still within the base include directory.
        $baseRealPath = realpath($this->baseIncludePath);
        if ($baseRealPath === false) {
            return null;
        }

        // Check that resolved path starts with the base path.
        if (strpos($realPath, $baseRealPath) !== 0) {
            return null;
        }

        return $realPath;
    }

    /**
     * Find the end of a block (FOR/WHILE/IF) and extract its body tokens.
     *
     * @param array $token The opening block token.
     * @param int $position The token's index.
     * @return array<int, array> Body tokens between start and end.
     */
    public function findEnd(array $token, int $position): array
    {
        $startKeyword = (string)($token['type'] ?? '');
        $endKeyword = $this->blockEnds[$startKeyword] ?? '';
        $tab = (int)($token['tab'] ?? 0);

        $loopTokens = [];
        $step = $position;
        $foundEnd = false;
        $iterations = 0;

        while (!$foundEnd && isset($this->tokens[$step]) && $iterations < self::MAX_ITERATIONS) {
            $iterations++;
            $loopToken = $this->tokens[$step];

            if (((int)($loopToken['tab'] ?? -1)) === $tab
                && strtoupper((string)($loopToken['type'] ?? '')) === $endKeyword
            ) {
                $foundEnd = true;
            }

            if ($step !== $position && !$foundEnd) {
                $loopTokens[] = $loopToken;
            }

            $this->removeToken($step);
            $step++;
        }

        return $loopTokens;
    }

    /**
     * Append tokens at a specific position in the token list.
     *
     * @param int $position
     * @param array<int, array> $tokens
     */
    public function appendTokens(int $position = 0, array $tokens = []): void
    {
        $part1 = array_slice($this->tokens, 0, $position);
        $part2 = array_slice($this->tokens, $position + 1);

        $this->tokens = array_merge($part1, $tokens, $part2);
    }

    /**
     * Replace a token at the given position with an empty HTML token (soft delete).
     *
     * @param int $position
     */
    public function removeToken(int $position): void
    {
        $this->tokens[$position] = [
            'type'    => 'HTML',
            'content' => '',
            'tab'     => 0,
        ];
    }

    /**
     * Replace a token at the given position with an HTML token carrying the value.
     *
     * @param int $position
     * @param mixed $value
     */
    public function setToken(int $position, $value): void
    {
        $this->tokens[$position] = [
            'type'    => 'HTML',
            'content' => (string)$value,
            'tab'     => 0,
        ];
    }

    /**
     * Normalize a directory path to ensure trailing separator.
     */
    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Export the final token list.
     *
     * @return array<int, array>
     */
    public function exportTokens(): array
    {
        return $this->tokens;
    }
}
