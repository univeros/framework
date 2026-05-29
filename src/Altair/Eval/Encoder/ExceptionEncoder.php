<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Encoder;

use Throwable;

/**
 * Turns a Throwable into a JSON-serialisable shape mirroring what `getTrace()`
 * exposes, walking the `previous` chain so a wrapped cause is visible too.
 *
 * The trace is rendered as one short line per frame
 * (`Class::method (file:line)`), and the chain is depth-capped at
 * {@see MAX_PREVIOUS_CHAIN} so a pathological wrap cannot blow up the payload.
 */
final class ExceptionEncoder
{
    public const int MAX_PREVIOUS_CHAIN = 10;

    public const int MAX_TRACE_FRAMES = 30;

    /**
     * @return array<string, mixed>
     */
    public static function encode(Throwable $throwable): array
    {
        $shape = [
            'class' => $throwable::class,
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'code' => $throwable->getCode(),
            'stack_trace' => self::trace($throwable),
        ];

        $chain = self::previousChain($throwable);
        if ($chain !== []) {
            $shape['previous'] = $chain;
        }

        return $shape;
    }

    /**
     * @return list<string>
     */
    private static function trace(Throwable $throwable): array
    {
        $frames = [];
        $count = 0;
        foreach ($throwable->getTrace() as $frame) {
            if ($count >= self::MAX_TRACE_FRAMES) {
                $frames[] = '...(truncated)';

                break;
            }

            $frames[] = self::renderFrame($frame);
            ++$count;
        }

        return $frames;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private static function renderFrame(array $frame): string
    {
        $callable = isset($frame['class'], $frame['type'], $frame['function'])
            ? \sprintf('%s%s%s', (string) $frame['class'], (string) $frame['type'], (string) $frame['function'])
            : (string) ($frame['function'] ?? '<unknown>');

        $location = isset($frame['file'], $frame['line'])
            ? \sprintf('%s:%d', (string) $frame['file'], (int) $frame['line'])
            : '<internal>';

        return $callable . ' (' . $location . ')';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function previousChain(Throwable $throwable): array
    {
        $chain = [];
        $previous = $throwable->getPrevious();
        $depth = 0;
        while ($previous instanceof Throwable && $depth < self::MAX_PREVIOUS_CHAIN) {
            $chain[] = [
                'class' => $previous::class,
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ];
            $previous = $previous->getPrevious();
            ++$depth;
        }

        return $chain;
    }
}
