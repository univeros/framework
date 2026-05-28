<?php

declare(strict_types=1);

namespace Altair\Tests\Sanitation\Resolver;

use Altair\Container\Container;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\Filter\MinFilter;
use Altair\Sanitation\Resolver\FilterResolver;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
class FilterResolverTest extends TestCase
{
    /**
     * @param mixed $entry
     */
    #[DataProvider('filterProvider')]
    public function testResolver(string|array $entry): void
    {
        $resolver = $this->getResolver();
        $rule = call_user_func($resolver, $entry);

        $this->assertTrue($rule instanceof FilterInterface);
    }

    public static function filterProvider(): array
    {
        return [
            [AlphaFilter::class],
            [['class' => AlphaFilter::class]],
            [['class' => MinFilter::class, ':min' => 1]]
        ];
    }

    protected function getResolver(): FilterResolver
    {
        return new FilterResolver(new Container());
    }
}
