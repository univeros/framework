<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

/**
 * Matches an IP address (IPv4 or IPv6) against a list of CIDR ranges or
 * exact addresses. A pattern without a `/` is treated as an exact-match
 * single host.
 */
final class CidrMatcher
{
    /**
     * @param list<string> $patterns CIDR ranges (e.g. "10.0.0.0/8", "2001:db8::/32") or exact IPs
     */
    public function __construct(
        private readonly array $patterns,
    ) {
    }

    public function matches(string $ip): bool
    {
        $address = @inet_pton($ip);
        if ($address === false) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if ($this->patternMatches($pattern, $ip, $address)) {
                return true;
            }
        }

        return false;
    }

    private function patternMatches(string $pattern, string $ip, string $address): bool
    {
        if (!str_contains($pattern, '/')) {
            return $pattern === $ip;
        }

        [$subnet, $maskBits] = explode('/', $pattern, 2);
        $subnetAddress = @inet_pton($subnet);
        if ($subnetAddress === false) {
            return false;
        }

        // IPv4 (4 bytes) and IPv6 (16 bytes) must match across the same family
        if (strlen($address) !== strlen($subnetAddress)) {
            return false;
        }

        $bits = (int) $maskBits;
        $maxBits = strlen($address) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }

        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($address, 0, $fullBytes) !== substr($subnetAddress, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr(0xFF << (8 - $remainder) & 0xFF);

        return ($address[$fullBytes] & $mask) === ($subnetAddress[$fullBytes] & $mask);
    }
}
