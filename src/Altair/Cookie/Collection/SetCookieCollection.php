<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie\Collection;

use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\Exception\InvalidCallException;
use Altair\Cookie\SetCookie;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Contracts\PairInterface;
use Altair\Structure\Contracts\VectorInterface;
use Altair\Structure\Map;
use Altair\Structure\Pair;
use Altair\Structure\Vector;
use Override;
use Psr\Http\Message\ResponseInterface;

class SetCookieCollection extends Map
{
    /**
     * Adds a SetCookie to the collection
     *
     *
     */
    public function putSetCookie(SetCookie $cookie): SetCookieCollection
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
     * @return MapInterface<string, SetCookie>
     */
    #[Override]
    public function put($key, $value): MapInterface
    {
        $pair = $this->lookupKey($key);

        if ($pair instanceof Pair) {
            $pair->value = new SetCookie($key, $value);
        } else {
            $this->adjustCapacity();
            $this->internal[] = new Pair($key, new SetCookie($key, $value));
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param iterable<string, SetCookie|string> $values
     *
     * @return MapInterface<string, SetCookie>
     */
    #[Override]
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $value) {
            if ($value instanceof SetCookie) {
                $this->putSetCookie($value);
            } else {
                $this->put($key, $value);
            }
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<string, SetCookie>
     */
    #[Override]
    public function sort(?callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator !== null) {
            usort(
                $pairs,
                static fn($a, $b) => $comparator($a->value->getValue(), $b->value->getValue())
            );
        } else {
            usort(
                $pairs,
                static fn($a, $b): int => $a->value->getValue() <=> $b->value->getValue()
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritDoc}
     *
     * @return VectorInterface<int, ?string>
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
    public function injectIntoResponseHeader(ResponseInterface $response): ResponseInterface
    {
        $response = $response->withoutHeader(SetCookieInterface::HEADER);
        foreach ($this->toArray() as $cookie) {
            $response = $response->withAddedHeader(SetCookieInterface::HEADER, (string) $cookie);
        }

        return $response;
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
     * @param array<int, Pair> $pairs
     *
     * @return array<string, string>
     */
    #[Override]
    protected function pairsToArray($pairs): array
    {
        $array = [];
        /** @var Pair $pair */
        foreach ($pairs as $pair) {
            $array[$pair->key] = (string) $pair->value;
        }

        return $array;
    }
}
