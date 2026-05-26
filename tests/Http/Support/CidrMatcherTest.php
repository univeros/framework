<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Support\CidrMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CidrMatcherTest extends TestCase
{
    public function testEmptyPatternListNeverMatches(): void
    {
        $this->assertFalse((new CidrMatcher([]))->matches('10.0.0.1'));
    }

    public function testExactIpv4MatchWithoutCidr(): void
    {
        $matcher = new CidrMatcher(['203.0.113.7']);

        $this->assertTrue($matcher->matches('203.0.113.7'));
        $this->assertFalse($matcher->matches('203.0.113.8'));
    }

    public function testExactIpv6MatchWithoutCidr(): void
    {
        $matcher = new CidrMatcher(['2001:db8::1']);

        $this->assertTrue($matcher->matches('2001:db8::1'));
        $this->assertFalse($matcher->matches('2001:db8::2'));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function ipv4InRangeProvider(): array
    {
        return [
            ['10.0.0.0/8',       '10.255.255.255'],
            ['10.0.0.0/8',       '10.0.0.0'],
            ['192.168.1.0/24',   '192.168.1.7'],
            ['192.168.0.0/16',   '192.168.255.254'],
            ['127.0.0.1/32',     '127.0.0.1'],
            ['0.0.0.0/0',        '8.8.8.8'],
        ];
    }

    #[DataProvider('ipv4InRangeProvider')]
    public function testIpv4InCidrRangeMatches(string $cidr, string $ip): void
    {
        $this->assertTrue((new CidrMatcher([$cidr]))->matches($ip));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function ipv4OutOfRangeProvider(): array
    {
        return [
            ['10.0.0.0/8',     '11.0.0.1'],
            ['192.168.1.0/24', '192.168.2.1'],
            ['127.0.0.1/32',   '127.0.0.2'],
        ];
    }

    #[DataProvider('ipv4OutOfRangeProvider')]
    public function testIpv4OutsideCidrRangeDoesNotMatch(string $cidr, string $ip): void
    {
        $this->assertFalse((new CidrMatcher([$cidr]))->matches($ip));
    }

    public function testIpv6CidrMatch(): void
    {
        $matcher = new CidrMatcher(['2001:db8::/32']);

        $this->assertTrue($matcher->matches('2001:db8::1'));
        $this->assertTrue($matcher->matches('2001:db8:ffff::ffff'));
        $this->assertFalse($matcher->matches('2001:db9::1'));
    }

    public function testMixedIpv4AndIpv6PatternsCoexist(): void
    {
        $matcher = new CidrMatcher(['10.0.0.0/8', '2001:db8::/32']);

        $this->assertTrue($matcher->matches('10.0.0.1'));
        $this->assertTrue($matcher->matches('2001:db8::1'));
        $this->assertFalse($matcher->matches('11.0.0.1'));
        $this->assertFalse($matcher->matches('2001:db9::1'));
    }

    public function testIpv4InIpv6PatternDoesNotMatchAcrossFamilies(): void
    {
        // 10.0.0.0/8 must not match an IPv6 address (different byte length)
        $this->assertFalse((new CidrMatcher(['10.0.0.0/8']))->matches('::ffff:10.0.0.1'));
    }

    public function testInvalidIpReturnsFalseRatherThanThrowing(): void
    {
        $matcher = new CidrMatcher(['10.0.0.0/8']);

        $this->assertFalse($matcher->matches('not-an-ip'));
        $this->assertFalse($matcher->matches(''));
    }

    public function testInvalidPatternIsIgnored(): void
    {
        $matcher = new CidrMatcher(['garbage', '10.0.0.0/8']);

        $this->assertTrue($matcher->matches('10.0.0.1'));
    }

    public function testInvalidMaskBitsRejected(): void
    {
        $this->assertFalse((new CidrMatcher(['10.0.0.0/40']))->matches('10.0.0.1'));
        $this->assertFalse((new CidrMatcher(['10.0.0.0/-1']))->matches('10.0.0.1'));
    }

    public function testZeroMaskMatchesEverythingWithinFamily(): void
    {
        $this->assertTrue((new CidrMatcher(['0.0.0.0/0']))->matches('1.2.3.4'));
        $this->assertTrue((new CidrMatcher(['::/0']))->matches('2001:db8::1'));
    }
}
