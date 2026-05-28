<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Support\PhpClassScanner;
use ReflectionClass;

/**
 * Builds {@see ToolDescriptor}s from classes carrying the {@see McpTool}
 * attribute. Built-in tools are passed as an explicit class list (fast, no
 * filesystem scan); user-defined tools are found by scanning directories.
 */
final readonly class AttributeToolDiscoverer
{
    public function __construct(private PhpClassScanner $scanner = new PhpClassScanner()) {}

    /**
     * @param list<class-string> $classNames
     *
     * @return list<ToolDescriptor>
     */
    public function fromClasses(array $classNames): array
    {
        $descriptors = [];
        foreach ($classNames as $className) {
            $descriptor = $this->describe($className);
            if ($descriptor instanceof ToolDescriptor) {
                $descriptors[] = $descriptor;
            }
        }

        return $descriptors;
    }

    /**
     * @param class-string $className
     */
    public function describe(string $className): ?ToolDescriptor
    {
        if (!class_exists($className)) {
            return null;
        }

        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(McpTool::class);
        if ($attributes === []) {
            return null;
        }

        if (!$reflection->implementsInterface(McpToolInterface::class)) {
            throw new McpException(\sprintf(
                '%s carries #[McpTool] but does not implement %s.',
                $className,
                McpToolInterface::class,
            ));
        }

        $attribute = $attributes[0]->newInstance();

        return new ToolDescriptor(
            name: $attribute->name,
            description: $attribute->description,
            className: $className,
            inputSchema: $this->loadSchema($attribute->inputSchema),
            outputSchema: $this->loadSchema($attribute->outputSchema),
        );
    }

    /**
     * Scan directories for `*.php` files declaring an #[McpTool] class.
     *
     * @param list<string> $directories
     *
     * @return list<class-string>
     */
    public function discoverClasses(array $directories): array
    {
        $classes = [];
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->scanner->classesIn($directory) as $fqcn) {
                if ($this->hasToolAttribute($fqcn)) {
                    $classes[] = $fqcn;
                }
            }
        }

        return $classes;
    }

    /**
     * @param class-string $fqcn
     */
    private function hasToolAttribute(string $fqcn): bool
    {
        if (!class_exists($fqcn)) {
            return false;
        }

        return (new ReflectionClass($fqcn))->getAttributes(McpTool::class) !== [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSchema(?string $path): ?array
    {
        if ($path === null) {
            return null;
        }

        if (!is_file($path)) {
            throw new McpException(\sprintf("Tool schema file '%s' does not exist.", $path));
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!\is_array($decoded)) {
            throw new McpException(\sprintf("Tool schema file '%s' is not a JSON object.", $path));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
