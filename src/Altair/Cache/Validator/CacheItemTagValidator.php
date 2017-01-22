<?php
namespace Altair\Cache\Validator;

use Altair\Cache\Contracts\CacheItemTagValidatorInterface;
use Altair\Cache\Traits\FailureReasonAwareTrait;

class CacheItemTagValidator implements CacheItemTagValidatorInterface
{
    use FailureReasonAwareTrait;

    /**
     * @inheritdoc
     */
    public function validate(string $tag): bool
    {
        if (!isset($tag[0])) {
            $this->reason = 'Cache tag length must be greater than zero';

            return false;
        }
        if (false !== strpbrk($tag, '{}()/\@:')) {
            $this->reason = sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag);

            return false;
        }

        return true;
    }
}
