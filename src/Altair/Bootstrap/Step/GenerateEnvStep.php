<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap\Step;

use Altair\Bootstrap\Contracts\PresetInterface;
use Altair\Bootstrap\Exception\BootstrapException;

use const DIRECTORY_SEPARATOR;

/**
 * Writes the project's `.env` from `.env.example`, setting the messenger
 * transport DSN to match the preset's queue choice. Secrets stay as obvious
 * placeholders (e.g. `APP_KEY=changeme`) — never auto-generated.
 */
final class GenerateEnvStep
{
    public function run(string $targetDir, PresetInterface $preset): void
    {
        $example = $targetDir . DIRECTORY_SEPARATOR . '.env.example';
        $contents = is_file($example) ? (string) file_get_contents($example) : "APP_ENV=dev\nAPP_KEY=changeme\n";

        $contents = $this->setVar($contents, 'MESSENGER_TRANSPORT_DSN', $this->queueDsn($preset->queue()));

        $envPath = $targetDir . DIRECTORY_SEPARATOR . '.env';
        if (file_put_contents($envPath, $contents) === false) {
            throw new BootstrapException(\sprintf("Failed to write '%s'.", $envPath));
        }
    }

    private function queueDsn(string $queue): string
    {
        return match ($queue) {
            'redis' => 'redis://localhost:6379/messages',
            'doctrine' => 'doctrine://default',
            default => 'sync://',
        };
    }

    private function setVar(string $contents, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        $line = $key . '=' . $value;

        if (preg_match($pattern, $contents) === 1) {
            // Callback form so `$` / `\` in the value aren't treated as backreferences.
            return preg_replace_callback($pattern, static fn(): string => $line, $contents) ?? $contents;
        }

        return rtrim($contents) . "\n" . $line . "\n";
    }
}
