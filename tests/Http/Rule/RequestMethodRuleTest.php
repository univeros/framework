<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Rule;

use Altair\Http\Rule\RequestMethodRule;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class RequestMethodRuleTest extends TestCase
{
    public function testOptionsRequestIsPassThroughByDefault(): void
    {
        $rule = new RequestMethodRule();
        $request = (new ServerRequest())->withMethod('OPTIONS');

        $this->assertFalse($rule($request));
    }

    public function testGetRequestRequiresAuthByDefault(): void
    {
        $rule = new RequestMethodRule();
        $request = (new ServerRequest())->withMethod('GET');

        $this->assertTrue($rule($request));
    }

    public function testCustomPassthroughMethodsAreHonored(): void
    {
        $rule = new RequestMethodRule(['passthrough' => ['GET', 'HEAD']]);

        $this->assertFalse($rule((new ServerRequest())->withMethod('GET')));
        $this->assertFalse($rule((new ServerRequest())->withMethod('HEAD')));
        $this->assertTrue($rule((new ServerRequest())->withMethod('POST')));
    }
}
