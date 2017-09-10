<?php

namespace Altair\Cache\Traits;

use Altair\Cache\Contracts\TagAwareCacheItemInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Cache\Validator\CacheItemTagValidator;

trait TagsAwareTrait
{
    /**
     * @var CacheItemTagValidator
     */
    protected $tagValidator;

    /**
     * @inheritdoc
     */
    public function withTag(string $tag): TagAwareCacheItemInterface
    {
        return $this->withTags([$tag]);
    }

    /**
     * @inheritdoc
     */
    public function withTags(array $tags): TagAwareCacheItemInterface
    {
        $cloned = clone $this;

        foreach ($tags as $tag) {
            if (is_string($tag) && isset($cloned->tags[$tag])) {
                continue;
            }

            if (!$cloned->tagValidator->validate($tag)) {
                throw new InvalidArgumentException($cloned->tagValidator->getFailureReason());
            }
            $cloned->tags[$tag] = $tag;
        }

        return $cloned;
    }
}
