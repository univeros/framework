<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Reflection;

use Altair\AgentSpec\Model\PackageDescriptor;

/**
 * Reads optional, hand-authored sidecar files that contribute free-form
 * content the generator cannot infer from PHP source:
 *
 *   <package>/.agent/purpose.md    one-paragraph override of composer.json description
 *   <package>/.agent/patterns.md   common code patterns (one or more fenced blocks separated by ---)
 *   <package>/.agent/stability.md  stability / compatibility note
 */
class SidecarReader
{
    public function readPurpose(PackageDescriptor $package): ?string
    {
        return $this->readFile($package, 'purpose.md');
    }

    public function readStability(PackageDescriptor $package): ?string
    {
        return $this->readFile($package, 'stability.md');
    }

    /**
     * Patterns sidecar is split on lines containing only `---` (with optional
     * surrounding whitespace), preserving each section verbatim.
     *
     * @return list<string>
     */
    public function readPatterns(PackageDescriptor $package): array
    {
        $raw = $this->readFile($package, 'patterns.md');
        if ($raw === null) {
            return [];
        }

        $sections = preg_split('/^\s*---\s*$/m', $raw);
        if ($sections === false) {
            return [trim($raw)];
        }

        $trimmed = [];
        foreach ($sections as $section) {
            $section = trim($section);
            if ($section !== '') {
                $trimmed[] = $section;
            }
        }

        return $trimmed;
    }

    private function readFile(PackageDescriptor $package, string $name): ?string
    {
        $path = $package->sourcePath . DIRECTORY_SEPARATOR . '.agent' . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $contents = trim($contents);

        return $contents === '' ? null : $contents;
    }
}
