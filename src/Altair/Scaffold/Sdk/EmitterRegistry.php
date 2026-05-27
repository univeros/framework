<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk;

use Altair\Scaffold\Sdk\Contracts\EmitterInterface;
use Altair\Scaffold\Sdk\Exception\SdkException;
use Altair\Scaffold\Sdk\Python\PythonEmitter;
use Altair\Scaffold\Sdk\TypeScript\TypeScriptEmitter;

/**
 * Language → emitter lookup. Ships TypeScript + Python out of the box;
 * a host can register more (Go, Rust, ...) by constructing the registry
 * with additional emitters.
 */
final readonly class EmitterRegistry
{
    /**
     * @param array<string, EmitterInterface> $emitters
     */
    public function __construct(
        private array $emitters,
    ) {}

    public static function default(): self
    {
        $emitters = [];
        foreach ([new TypeScriptEmitter(), new PythonEmitter()] as $emitter) {
            $emitters[$emitter->language()] = $emitter;
        }

        return new self($emitters);
    }

    public function get(string $language): EmitterInterface
    {
        $key = strtolower($language);
        if (!isset($this->emitters[$key])) {
            throw new SdkException(\sprintf(
                "Unknown SDK language '%s'. Available: %s.",
                $language,
                implode(', ', $this->available()),
            ));
        }

        return $this->emitters[$key];
    }

    public function has(string $language): bool
    {
        return isset($this->emitters[strtolower($language)]);
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->emitters);
    }
}
