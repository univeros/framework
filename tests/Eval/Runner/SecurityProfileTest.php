<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Eval\Runner;

use Altair\Eval\EvalRequest;
use Altair\Eval\Runner\SecurityProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityProfile::class)]
#[CoversClass(EvalRequest::class)]
final class SecurityProfileTest extends TestCase
{
    public function testDefaultRequestEnablesMemoryAndBasedirAndDisabledFunctions(): void
    {
        $profile = new SecurityProfile(new EvalRequest('return 1;', '/srv/app'));

        $flags = $profile->phpFlags('/srv/app');
        $joined = implode(' ', $flags);

        self::assertStringContainsString('memory_limit=128M', $joined);
        self::assertStringContainsString('open_basedir=/srv/app', $joined);
        self::assertStringContainsString('disable_functions=', $joined);
        self::assertStringContainsString('exec,shell_exec,passthru,system', $joined);
    }

    public function testNetworkDisabledAddsNetworkFunctionsToDisabledList(): void
    {
        $profile = new SecurityProfile(new EvalRequest('x', '/srv/app', allowNetwork: false));

        $joined = implode(' ', $profile->phpFlags('/srv/app'));

        self::assertStringContainsString('curl_exec', $joined);
        self::assertStringContainsString('fsockopen', $joined);
        self::assertStringContainsString('allow_url_fopen=0', $joined);
    }

    public function testNetworkEnabledDropsNetworkFunctionsAndUrlFopenFlag(): void
    {
        $profile = new SecurityProfile(new EvalRequest('x', '/srv/app', allowNetwork: true));

        $joined = implode(' ', $profile->phpFlags('/srv/app'));

        self::assertStringNotContainsString('curl_exec', $joined);
        self::assertStringNotContainsString('allow_url_fopen=0', $joined);
        self::assertStringContainsString('exec,shell_exec', $joined);
    }

    public function testUnsafeLiftsEveryIniGuard(): void
    {
        $profile = new SecurityProfile(new EvalRequest('x', '/srv/app', unsafe: true));

        self::assertSame([], $profile->phpFlags('/srv/app'));
    }

    public function testEnvVarsCarryHostCooperativeGuards(): void
    {
        $profile = new SecurityProfile(new EvalRequest('x', '/srv/app', allowWrites: true, allowNetwork: true, unsafe: true));

        self::assertSame([
            'ALTAIR_EVAL_ALLOW_WRITES' => '1',
            'ALTAIR_EVAL_ALLOW_NETWORK' => '1',
            'ALTAIR_EVAL_UNSAFE' => '1',
        ], $profile->envVars());
    }

    public function testEvalRequestClampsAbusiveLimitsIntoSafeRange(): void
    {
        $bigTimeout = new EvalRequest('x', '/r', timeoutMs: 999_999);
        $bigMemory = new EvalRequest('x', '/r', memoryLimitMb: 10_000);
        $negative = new EvalRequest('x', '/r', timeoutMs: -1, memoryLimitMb: -1);

        self::assertSame(EvalRequest::MAX_TIMEOUT_MS, $bigTimeout->timeoutMs);
        self::assertSame(EvalRequest::MAX_MEMORY_MB, $bigMemory->memoryLimitMb);
        self::assertSame(EvalRequest::MIN_TIMEOUT_MS, $negative->timeoutMs);
        self::assertSame(EvalRequest::MIN_MEMORY_MB, $negative->memoryLimitMb);
    }
}
