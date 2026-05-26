<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Responder;

use Altair\Http\Base\Payload;
use Altair\Http\Responder\RedirectResponder;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class RedirectResponderTest extends TestCase
{
    public function testAddsLocationHeaderWhenRedirectSettingIsPresent(): void
    {
        $payload = (new Payload())->withSetting('redirect', '/new-home');

        $response = (new RedirectResponder())(new ServerRequest(), new Response(), $payload);

        $this->assertSame('/new-home', $response->getHeaderLine('Location'));
    }

    public function testNoLocationHeaderWhenRedirectSettingMissing(): void
    {
        $response = (new RedirectResponder())(new ServerRequest(), new Response(), new Payload());

        $this->assertFalse($response->hasHeader('Location'));
    }
}
