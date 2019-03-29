<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Contracts;

use Altair\Cache\Exception\InvalidArgumentException;
use Psr\Cache\CacheItemInterface;

interface TagAwareCacheItemInterface extends CacheItemInterface
{
    /**
     * Returns a cloned instance with new specified tag.
     *
     * @param string $tag
     *
     * @throws InvalidArgumentException if the tag is not valid
     *
     * @return TagAwareCacheItemInterface
     */
    public function withTag(string $tag): TagAwareCacheItemInterface;

    /**
     * Returns a cloned instance with new specified tags.
     *
     * @param array $tags
     *
     * @throws InvalidArgumentException if any is not valid
     *
     * @return TagAwareCacheItemInterface
     */
    public function withTags(array $tags): TagAwareCacheItemInterface;
}
