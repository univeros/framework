<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Cache;

use Altair\Container\Attribute\Autowire;
use Altair\Container\Attribute\Factory;
use Altair\Container\Attribute\Inject;
use Altair\Container\Contracts\ReflectionCacheInterface;
use Altair\Container\Reflection\ClassMetadata;
use Altair\Container\Reflection\ParameterMetadata;
use Override;
use Throwable;

/**
 * Opcode-friendly reflection cache that persists extracted {@see ClassMetadata}
 * across requests. Unlike the previous container's `var_export()` of live
 * `Reflection*` objects, this serializes plain data — the only entries it skips
 * are the rare ones whose parameter defaults are themselves unserializable.
 *
 * Pass an application-specific, non-world-writable directory in production: the
 * default under the system temp dir is shared across apps on the host (risking
 * cross-app metadata bleed) and reading is hardened with an allowed-classes
 * whitelist against tampered cache files.
 */
final readonly class FileReflectionCache implements ReflectionCacheInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? sys_get_temp_dir(), '/\\') . '/altair-container';

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0o775, true);
        }
    }

    #[Override]
    public function get(string $key): ?ClassMetadata
    {
        $file = $this->fileFor($key);
        if (!is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        try {
            // Whitelist only the classes a serialized ClassMetadata can legitimately
            // contain, so a tampered cache file cannot trigger object injection.
            $value = unserialize($contents, [
                'allowed_classes' => [
                    ClassMetadata::class,
                    ParameterMetadata::class,
                    Factory::class,
                    Inject::class,
                    Autowire::class,
                ],
            ]);
        } catch (Throwable) {
            return null;
        }

        return $value instanceof ClassMetadata ? $value : null;
    }

    #[Override]
    public function put(string $key, ClassMetadata $metadata): void
    {
        try {
            $contents = serialize($metadata);
        } catch (Throwable) {
            // A parameter default that cannot be serialized (e.g. a closure):
            // skip persistence and fall back to live reflection next time.
            return;
        }

        @file_put_contents($this->fileFor($key), $contents, LOCK_EX);
    }

    private function fileFor(string $key): string
    {
        return $this->directory . '/' . md5($key) . '.cache';
    }
}
