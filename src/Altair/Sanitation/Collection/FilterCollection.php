<?php
namespace Altair\Sanitation\Collection;

use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Map;
use Altair\Sanitation\Exception\InvalidArgumentException;

class FilterCollection extends Map
{
    /**
     * @inheritdoc
     */
    public function put($key, $value): MapInterface
    {
        $this->parseFilters($value);

        return parent::put($key, $value);
    }

    /**
     * @inheritdoc
     */
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
     *
     * @param mixed $key
     */
    protected function filterKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Keys of filters must be of type string to match a name of the subject. Type "%s" given.',
                    gettype($key)
                )
            );
        }
    }

    /**
     * Ensures filters are implementing the appropriate interface.
     *
     * @param mixed $filters
     */
    protected function parseFilters($filters)
    {
        if (is_string($filters)) {
            if (!in_array(FilterInterface::class, class_implements($filters))) {
                throw new InvalidArgumentException(
                    sprintf(
                        '"%s" does not implement %s.',
                        $filters,
                        FilterInterface::class
                    )
                );
            }
        } else {
            foreach ($filters as $filter) {
                if (is_string($filter)) {
                    $filter = ['class' => $filter];
                }
                $class = $filter['class'] ?? null;
                if ($class === null || !in_array(FilterInterface::class, class_implements($class))) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'A definition of a filter as array must have a "class" key and must implement %s.',
                            FilterInterface::class
                        )
                    );
                }
            }
        }
    }
}
