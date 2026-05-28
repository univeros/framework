<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Collection;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Exception\InvalidArgumentException;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Map;
use Override;
use Traversable;

/**
 * @extends Map<string, mixed>
 */
class FilterCollection extends Map
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function put(mixed $key, mixed $value): MapInterface
    {
        $this->parseFilters($value);

        return parent::put($key, $value);
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed>|Traversable<string, mixed> $values
     */
    #[Override]
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $filters) {
            $this->filterKey($key);
            $this->parseFilters($filters);
        }

        return parent::putAll($values);
    }

    /**
     * Ensures the key is a valid string. Keys supposed to be attributes or keys of the 'subject' to filter.
     */
    protected function filterKey(mixed $key): void
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Keys of filters must be of type string to match a name of the subject. Type "%s" given.',
                    \gettype($key)
                )
            );
        }
    }

    /**
     * Ensures filters are implementing the appropriate interface.
     */
    protected function parseFilters(mixed $filters): void
    {
        if (\is_string($filters)) {
            if (!$this->implementsFilterInterface($filters)) {
                throw new InvalidArgumentException(
                    \sprintf(
                        '"%s" does not implement %s.',
                        $filters,
                        FilterInterface::class
                    )
                );
            }
        } else {
            foreach ($filters as $filter) {
                if (\is_string($filter)) {
                    $filter = ['class' => $filter];
                }

                $class = $filter['class'] ?? null;
                if (!\is_string($class) || !$this->implementsFilterInterface($class)) {
                    throw new InvalidArgumentException(
                        \sprintf(
                            'A definition of a filter as array must have a "class" key and must implement %s.',
                            FilterInterface::class
                        )
                    );
                }
            }
        }
    }

    /**
     * Resolves whether the given class name implements the filter contract.
     */
    private function implementsFilterInterface(string $class): bool
    {
        $implemented = class_implements($class);

        return $implemented !== false && \in_array(FilterInterface::class, $implemented, false);
    }
}
