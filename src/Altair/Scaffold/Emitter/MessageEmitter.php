<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\QueueDispatchSpec;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Emits a readonly message DTO class for a single queue: entry.
 *
 * The class lives in the namespace derived from the spec's `message`
 * FQCN. Each declared field becomes a constructor-promoted readonly
 * property typed natively when possible (scalars) or as a class
 * reference for FQCN-typed fields.
 */
class MessageEmitter
{
    public function emit(QueueDispatchSpec $queue): EmittedFile
    {
        $messageFqcn = $queue->message;
        $shortName = $this->shortNameOf($messageFqcn);
        $namespace = $this->namespaceOf($messageFqcn);

        $header = PhpHeader::render($namespace);
        $constructor = $this->renderConstructor($queue);

        $body = <<<PHP
            /**
             * Generated message DTO for queue entry "{$queue->name}".
             */
            final readonly class {$shortName}
            {
            {$constructor}
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->relativePath($messageFqcn),
            contents: $header . $body,
            kind: EmittedFileKind::Message,
        );
    }

    private function renderConstructor(QueueDispatchSpec $queue): string
    {
        if ($queue->fields === []) {
            return "    public function __construct() {}\n";
        }

        $lines = ['    public function __construct('];
        $last = \count($queue->fields) - 1;
        $i = 0;
        foreach ($queue->fields as $name => $type) {
            $phpType = $this->mapType($type);
            $sep = $i === $last ? '' : ',';
            $lines[] = \sprintf('        public %s $%s%s', $phpType, $name, $sep);
            $i++;
        }
        $lines[] = '    ) {}';

        return implode("\n", $lines);
    }

    private function mapType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'int',
            'float'           => 'float',
            'bool', 'boolean' => 'bool',
            'string'          => 'string',
            default           => '\\' . ltrim($type, '\\'),
        };
    }

    private function relativePath(string $fqcn): string
    {
        $relative = str_replace('\\', '/', ltrim($fqcn, '\\'));
        if (str_starts_with($relative, 'App/')) {
            return 'app/' . substr($relative, 4) . '.php';
        }

        return 'app/' . $relative . '.php';
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    private function shortNameOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
