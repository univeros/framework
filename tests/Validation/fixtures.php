<?php
namespace Altair\Tests\Validation;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Validation\Collection\RuleCollection;
use Altair\Validation\Contracts\RuleInterface;
use Altair\Validation\Contracts\ValidatableInterface;
use Altair\Validation\Rule\AlphaRule;

class RuleA implements RuleInterface
{
    public function __invoke(PayloadInterface $payload, callable $next)
    {
        $payload = $payload->withAttribute(self::class, 'A passed');
        return $next($payload);
    }
    public function assert($value): bool
    {
        return true;
    }
}

class RuleB implements RuleInterface
{
    public function __invoke(PayloadInterface $payload, callable $next)
    {
        return $next($payload->withAttribute(self::class, 'B passed'));
    }
    public function assert($value): bool
    {
        return true;
    }
}

class ValidEntity implements ValidatableInterface
{
    public $firstName = 'antonio';
    public $lastName = 'ramirez';

    public function getRules(): RuleCollection
    {
        return (new RuleCollection())
            ->put('firstName', AlphaRule::class)
            ->put('firstName, lastName', [AlphaRule::class]); // test multiple keys
    }
}

class InvalidEntity implements ValidatableInterface
{
    public $firstName = '4nt0n10';
    public $lastName = 'ramirez';
    public $alias = '4alias';

    public function getRules(): RuleCollection
    {
        return new RuleCollection(
            [
                'lastName, firstName, alias' => [AlphaRule::class]
            ]
        );
    }
}
