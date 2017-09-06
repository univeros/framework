<?php
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
use Psr\Http\Message\RequestInterface;

class CookieCollection extends Map
{
    /**
     * Adds a cookie to the collection
     *
     * @param Cookie $cookie
     *
     * @return CookieCollection
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
     * {@inheritdoc}
     */
    public function put($key, $value): MapInterface
    {
        $pair = $this->lookupKey($key);

        if ($pair) {
            $pair->value = new Cookie($key, $value);
        } else {
            $this->adjustCapacity();
            $this->internal[] = new Pair($key, new Cookie($key, $value));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $value) {
            if ($value instanceof Cookie) {
                $this->put($value->getName(), $value->getValue());
            } else {
                $this->put($key, $value);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sort(callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator) {
            usort(
                $pairs,
                function ($a, $b) use ($comparator) {
                    return $comparator($a->value->getValue(), $b->value->getValue());
                }
            );
        } else {
            usort(
                $pairs,
                function ($a, $b) {
                    return $a->value->getValue() <=> $b->value->getValue();
                }
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritdoc}
     */
    public function values(): VectorInterface
    {
        $sequence = new Vector();

        foreach ($this->internal as $pair) {
            $sequence->push($pair->value->getValue());
        }

        return $sequence;
    }

    /**
     * {@inheritdoc}
     */
    public function sum()
    {
        throw new InvalidCallException(sprintf('This method is not supported: %s', __FUNCTION__));
    }

    /**
     * Injects cookie collection into request header.
     *
     * @param RequestInterface $request
     *
     * @return RequestInterface
     */
    public function injectIntoRequestHeader(RequestInterface $request): RequestInterface
    {
        return $request->withHeader(CookieInterface::HEADER, implode('; ', $this->toArray()));
    }

    /**
     * Returns item if a value is found.
     *
     * @param mixed $value
     *
     * @return PairInterface|null
     */
    protected function lookupValue($value): ?PairInterface
    {
        foreach ($this->internal as $pair) {
            if ($pair->value->getValue() === $value) {
                return $pair;
            }
        }
    }

    /**
     * Converts pairs to array.
     *
     * @param $pairs
     *
     * @return array
     */
    protected function pairsToArray($pairs): array
    {
        $array = [];
        foreach ($pairs as $pair) {
            $array[$pair->value->getName()] = $pair->value->getValue();
        }

        return $array;
    }
}
