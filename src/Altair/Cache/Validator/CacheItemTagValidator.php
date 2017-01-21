<?php
namespace Altair\Cache\Validator;

use Altair\Cache\Contracts\CacheItemTagValidatorInterface;

class CacheItemTagValidator implements CacheItemTagValidatorInterface
{
    /**
     * @inheritdoc
     */
    public function validate(string $tag, string &$reason): bool
    {
        if (!isset($tag[0])) {
            $reason = 'Cache tag length must be greater than zero';

            return false;
        }
        if (false !== strpbrk($tag, '{}()/\@:')) {
            $reason = sprintf('Cache tag "%s" contains reserved characters {}()/\@:', $tag);

            return false;
        }

        return true;
    }
}
