<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Session;

use Altair\Session\SessionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(SessionManager::class)]
final class SessionManagerTest extends TestCase
{
    public function testGetCookieParamsReturnsArrayWithoutRecursing(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn([]);

        $manager = new SessionManager($request);

        $params = $manager->getCookieParams();

        self::assertIsArray($params);
        self::assertArrayHasKey('lifetime', $params);
        self::assertArrayHasKey('path', $params);
        self::assertArrayHasKey('domain', $params);
        self::assertArrayHasKey('secure', $params);
        self::assertArrayHasKey('httponly', $params);
    }

    public function testSetCookieParamsPersistsThroughGetCookieParams(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn([]);

        $manager = new SessionManager($request);
        $manager->setCookieParams(['path' => '/altair', 'domain' => '.example.com']);

        $params = $manager->getCookieParams();

        self::assertSame('/altair', $params['path']);
        self::assertSame('.example.com', $params['domain']);
    }
}
