<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Collection;

use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Map;
use Altair\Validation\Contracts\RuleInterface;
use Altair\Validation\Exception\InvalidArgumentException;

class RuleCollection extends Map
{
    /**
     * @inheritdoc
     */
    public function put($key, $value): MapInterface
    {
        $this->filterRules($value);

        return parent::put($key, $value);
    }

    /**
     * @inheritdoc
     */
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $rules) {
            $this->filterKey($key);
            $this->filterRules($rules);
        }

        return parent::putAll($values);
    }

    /**
     * Ensures the key is a valid string. Keys supposed to be attributes or keys of the 'subject' to validate.
     *
     * @param mixed $key
     */
    protected function filterKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Keys of rules must be of type string to match a name of the subject. Type "%s" given.',
                    gettype($key)
                )
            );
        }
    }

    /**
     * Ensures rules are implementing the appropriate interface.
     *
     * @param mixed $rules
     */
    protected function filterRules($rules)
    {
        if (is_string($rules)) {
            if (!in_array(RuleInterface::class, class_implements($rules))) {
                throw new InvalidArgumentException(
                    sprintf(
                        '"%s" does not implement %s.',
                        $rules,
                        RuleInterface::class
                    )
                );
            }
        } else {
            foreach ($rules as $rule) {
                if (is_string($rule)) {
                    $rule = ['class' => $rule];
                }
                $class = $rule['class'] ?? null;
                if ($class === null || !in_array(RuleInterface::class, class_implements($class))) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'A definition of a rule as array must have a "class" key and must implement %s.',
                            RuleInterface::class
                        )
                    );
                }
            }
        }
    }
}
