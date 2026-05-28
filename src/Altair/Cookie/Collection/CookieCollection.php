<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie\Collection;

use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Cookie;
use Altair\Cookie\Exception\InvalidCallException;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Contracts\PairInterface;
use Altair\Structure\Contracts\VectorInterface;
use Altair\Structure\Map;
use Altair\Structure\Pair;
use Altair\Structure\Vector;
use Override;
use Psr\Http\Message\RequestInterface;

class CookieCollection extends Map
{
    /**
     * Adds a cookie to the collection
     *
     *
     */
    public function putCookie(Cookie $cookie): CookieCollection
    {
        $pair = $this->lookupKey($cookie->getName());

        if ($pair instanceof Pair) {
            $pair->value = $cookie;
        } else {
            $this->adjustCapacity();
            $this->internal[] = new Pair($cookie->getName(), $cookie);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $key
     * @param ?string $value
     *
     * @return MapInterface<string, Cookie>
     */
    #[Override]
    public function put($key, $value): MapInterface
    {
        $pair = $this->lookupKey($key);

        if ($pair instanceof PairInterface) {
            $pair->value = new Cookie($key, $value);
        } else {
            $this->adjustCapacity();
            $this->internal[] = new Pair($key, new Cookie($key, $value));
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param iterable<string, Cookie|string> $values
     *
     * @return MapInterface<string, Cookie>
     */
    #[Override]
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Cookie) {
                $this->putCookie($value);
            } else {
                $this->put($key, $value);
            }
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<string, Cookie>
     */
    #[Override]
    public function sort(?callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator !== null) {
            usort(
                $pairs,
                fn($a, $b) => $comparator($a->value->getValue(), $b->value->getValue())
            );
        } else {
            usort(
                $pairs,
                fn($a, $b): int => $a->value->getValue() <=> $b->value->getValue()
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritDoc}
     *
     * @return VectorInterface<?string>
     */
    #[Override]
    public function values(): VectorInterface
    {
        $sequence = new Vector();

        foreach ($this->internal as $pair) {
            $sequence->push($pair->value->getValue());
        }

        return $sequence;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function sum(): never
    {
        throw new InvalidCallException(\sprintf('This method is not supported: %s', __FUNCTION__));
    }

    /**
     * Injects cookie collection into request header.
     *
     *
     */
    public function injectIntoRequestHeader(RequestInterface $request): RequestInterface
    {
        return $request->withHeader(CookieInterface::HEADER, implode('; ', $this->toArray()));
    }

    /**
     * Returns item if a value is found.
     *
     * @param mixed $value
     */
    #[Override]
    protected function lookupValue($value): ?PairInterface
    {
        foreach ($this->internal as $pair) {
            if ($pair->value->getValue() === $value) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * Converts pairs to array.
     *
     * @param array<int, Pair<string, Cookie>> $pairs
     *
     * @return array<string, string>
     */
    #[Override]
    protected function pairsToArray($pairs): array
    {
        $array = [];
        /** @var Pair<string, Cookie> $pair */
        foreach ($pairs as $pair) {
            $array[$pair->key] = (string) $pair->value;
        }

        return $array;
    }
}
