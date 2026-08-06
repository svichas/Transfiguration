<?php

declare(strict_types=1);

namespace Transfiguration\Core;

/**
 * Registry of callbacks for custom template tags.
 */
class Hooks
{
    /**
     * @param array<string, array{method: callable}> $hooks
     */
    public function __construct(private array $hooks = [])
    {
        foreach ($hooks as $key => $hook) {
            $this->hooks[strtoupper($key)] = $hook;
        }
    }

    /**
     * Return all registered hooks.
     *
     * @return array<string, array{method: callable}>
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    /**
     * Check if a token matches a registered hook and return the hook's result.
     *
     * @param array{type?: string, content?: string, tab?: int} $token
     * @param string $content The evaluated content to pass to the hook.
     * @return mixed The hook result, or false if no hook matched.
     */
    public function checkHooks(array $token = [], string $content = ''): mixed
    {
        $tokenType = strtoupper((string) ($token['type'] ?? ''));

        if (!isset($this->hooks[$tokenType])) {
            return false;
        }

        $hook = $this->hooks[$tokenType];

        if (!isset($hook['method']) || !is_callable($hook['method'])) {
            return false;
        }

        return ($hook['method'])($content);
    }
}