<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Rule;

use Altair\Http\Rule\RequestPathRule;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase;

class RequestPathRuleTest extends TestCase
{
    public function testReturnsTrueWhenRequestPathMatchesConfiguredPath(): void
    {
        $rule = new RequestPathRule(['path' => ['/admin']]);
        $request = (new ServerRequest())->withUri(new Uri('/admin/users'));

        $this->assertTrue($rule($request));
    }

    public function testReturnsFalseWhenRequestPathDoesNotMatch(): void
    {
        $rule = new RequestPathRule(['path' => ['/admin']]);
        $request = (new ServerRequest())->withUri(new Uri('/public'));

        $this->assertFalse($rule($request));
    }

    public function testPassthroughPathsTakePrecedenceOverProtectedPath(): void
    {
        $rule = new RequestPathRule([
            'path' => ['/admin'],
            'passthrough' => ['/admin/health'],
        ]);

        $this->assertFalse($rule((new ServerRequest())->withUri(new Uri('/admin/health'))));
        $this->assertTrue($rule((new ServerRequest())->withUri(new Uri('/admin/users'))));
    }
}
