<?php
namespace Altair\Tests\Validation;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Validation\Collection\RuleCollection;
use Altair\Validation\Contracts\RuleInterface;
use Altair\Validation\Contracts\ValidatableInterface;
use Altair\Validation\Rule\AlphaNumRule;
use Altair\Validation\Rule\AlphaRule;

class RuleA implements RuleInterface
{
    #[\Override]
    public function __invoke(PayloadInterface $payload, callable $next)
    {
        $payload = $payload->withAttribute(self::class, 'A passed');
        return $next($payload);
    }

    #[\Override]
    public function assert($value): bool
    {
        return true;
    }
}

class RuleB implements RuleInterface
{
    #[\Override]
    public function __invoke(PayloadInterface $payload, callable $next)
    {
        return $next($payload->withAttribute(self::class, 'B passed'));
    }

    #[\Override]
    public function assert($value): bool
    {
        return true;
    }
}

class ValidEntity implements ValidatableInterface
{
    public $firstName = 'antonio';

    public $lastName = 'ramirez';

    #[\Override]
    public function getRules(): RuleCollection
    {
        return (new RuleCollection())
            ->put('firstName', AlphaRule::class)
            ->put('firstName, lastName', [AlphaRule::class, ['class' => AlphaNumRule::class]]); // test multiple keys
    }
}

class InvalidEntity implements ValidatableInterface
{
    public $firstName = '4nt0n10';

    public $lastName = 'ramirez';

    public $alias = '4alias';

    #[\Override]
    public function getRules(): RuleCollection
    {
        return new RuleCollection(
            [
                'lastName, firstName, alias' => [AlphaRule::class]
            ]
        );
    }
}
