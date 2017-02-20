<?php
namespace Altair\Tests\Sanitation\Resolver;

use Altair\Container\Container;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\Filter\MinFilter;
use Altair\Sanitation\Resolver\FilterResolver;
use PHPUnit\Framework\TestCase;

class FilterResolverTest extends TestCase
{
    /**
     * @dataProvider filterProvider
     * @param mixed $entry
     */
    public function testResolver($entry)
    {
        $resolver = $this->getResolver();
        $rule = call_user_func($resolver, $entry);

        $this->assertTrue($rule instanceof FilterInterface);
    }

    public function filterProvider()
    {
        return [
            [AlphaFilter::class],
            [['class' => AlphaFilter::class]],
            [['class' => MinFilter::class, ':min' => 1]]
        ];
    }
    protected function getResolver()
    {
        return new FilterResolver(new Container());
    }
}
