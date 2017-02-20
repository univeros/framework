<?php
namespace Altair\Tests\Sanitation;

use Altair\Container\Container;
use Altair\Middleware\Payload;
use Altair\Sanitation\Contracts\PayloadInterface;
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\FiltersRunner;
use Altair\Sanitation\Resolver\FilterResolver;
use Altair\Structure\Queue;
use PHPUnit\Framework\TestCase;

class FiltersRunnerTest extends TestCase
{
    public function testRunner()
    {
        $runner = $this->getFiltersRunner();
        $payload = call_user_func($runner, $this->getPayload());
        $this->assertEquals('A executed', $payload->getAttribute(FilterA::class));
        $this->assertEquals('B executed', $payload->getAttribute(FilterB::class));

        $subject = $payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);
        $this->assertEquals('A:alphaFilter:B', $subject->test);

        $runner->withFilters([FilterA::class, FilterB::class]);
        $payload = call_user_func($runner, $this->getPayload());
        $this->assertEquals('A executed', $payload->getAttribute(FilterA::class));
        $this->assertEquals('B executed', $payload->getAttribute(FilterB::class));

        $subject = $payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);
        $this->assertEquals('A:alphaFilter4:B', $subject->test);
    }

    protected function getFiltersRunner()
    {
        $queue = new Queue(
            [
                AlphaFilter::class,
                ['class' => FilterA::class],
                ['class' => FilterB::class],
            ]
        );

        $resolver = new FilterResolver(new Container());

        return new FiltersRunner($resolver, $queue);
    }

    protected function getPayload()
    {
        return (new Payload())
            ->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, ['test' => 'alphaFilter4'])
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test');
    }
}
