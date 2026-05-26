<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec;

use Altair\Scaffold\Exception\SpecParseException;
use Altair\Scaffold\Spec\Ast\Spec;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Loads one or many spec files from a path (single file or directory).
 *
 * Keeps directory traversal out of the CLI command so it stays focused on
 * argument shape.
 */
class SpecLoader
{
    public function __construct(
        private readonly Parser $parser = new Parser(),
        private readonly Validator $validator = new Validator(),
    ) {}

    /**
     * @return list<Spec>
     */
    public function load(string $path, bool $validate = true): array
    {
        if (is_file($path)) {
            return [$this->loadFile($path, $validate)];
        }

        if (is_dir($path)) {
            $specs = [];
            foreach ($this->discoverYamlFiles($path) as $file) {
                $specs[] = $this->loadFile($file, $validate);
            }

            return $specs;
        }

        throw new SpecParseException(\sprintf("Spec path '%s' does not exist.", $path));
    }

    private function loadFile(string $path, bool $validate): Spec
    {
        $spec = $this->parser->parseFile($path);

        if ($validate) {
            $this->validator->assertValid($spec);
        }

        return $spec;
    }

    /**
     * @return list<string>
     */
    private function discoverYamlFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $directory,
            FilesystemIterator::SKIP_DOTS,
        ));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && \in_array(strtolower($file->getExtension()), ['yaml', 'yml'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
