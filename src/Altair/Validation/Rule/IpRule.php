<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Altair\Validation\Exception\InvalidArgumentException;

class IpRule extends AbstractRule
{
    /**
     * @var int
     */
    protected $options;
    /**
     * @var array|null
     */
    protected $range;

    /**
     * IpRule constructor.
     *
     * @param int|null $options
     * @param string|null $range
     */
    public function __construct(int $options = null, string $range = null)
    {
        $this->options = $options;
        $this->range = $range !== null ? $this->parseRange($range) : null;
    }

    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        return $this->assertAddress($value) && $this->assertNetwork($value);
    }

    /**
     * @inheritDoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid IP address.', $value);
    }

    /**
     * @param string $value
     *
     * @return array|null
     */
    protected function parseRange(string $value): ?array
    {
        if ($value === '*' || $value === '*.*.*.*' || $value === '0.0.0.0-255.255.255.255') {
            return null;
        }
        $range = ['min' => null, 'max' => null, 'mask' => null];

        if (mb_strpos($value, '-') !== false) {
            [$range['min'], $range['max']] = explode('-', $value);
        } elseif (mb_strpos($value, '*') !== false) {
            $range = $this->parseRangeUsingWildcards($value, $range);
        } elseif (mb_strpos($value, '/') !== false) {
            $range = $this->parseRangeUsingCidr($value, $range);
        } else {
            throw new InvalidArgumentException('Invalid network range.');
        }

        if (!$this->assertAddress($range['min'])) {
            throw new InvalidArgumentException('Invalid network range.');
        }
        if (isset($range['max']) && !$this->assertAddress($range['max'])) {
            throw new InvalidArgumentException('Invalid network range.');
        }

        return $range;
    }

    /**
     * @param string $value
     * @param array $range
     *
     * @return array
     */
    protected function parseRangeUsingWildcards(string $value, array $range): array
    {
        $value = $this->fillAddress($value);
        $range['min'] = str_replace('*', '0', $value);
        $range['max'] = str_replace('*', '255', $value);

        return $range;
    }

    /**
     * @param string $value
     * @param array $range
     *
     * @return array
     */
    protected function parseRangeUsingCidr(string $value, array $range): array
    {
        list($min, $max) = explode('/', $value);
        $range['min'] = $this->fillAddress($min, '0');
        $isMask = mb_strpos($max, '.') !== false;
        if ($isMask && $this->assertAddress($max)) {
            $range['mask'] = sprintf('%032b', ip2long($max));
            return $range;
        }

        if ($isMask || $max < 8 || $max > 30) {
            throw new InvalidArgumentException('Invalid network mask.');
        }

        $range['mask'] = sprintf('%032b', ip2long(long2ip(~((2 ** (32 - $max)) - 1))));

        return $range;
    }

    /**
     * @param string $input
     * @param string $char
     *
     * @return string
     */
    protected function fillAddress(string $input, string $char = '*'): string
    {
        while (mb_substr_count($input, '.') < 3) {
            $input .= '.' . $char;
        }

        return $input;
    }

    /**
     * @param string $address
     *
     * @return bool
     */
    protected function assertAddress(string $address): bool
    {
        return (bool)filter_var($address, FILTER_VALIDATE_IP, ['flags' => $this->options,]);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function assertNetwork(string $value): bool
    {
        if ($this->range === null) {
            return true;
        }
        if (isset($this->range['mask'])) {
            return $this->assertSubnet($value);
        }
        $value = sprintf('%u', ip2long($value));
        return bccomp($value, sprintf('%u', ip2long($this->range['min']))) >= 0
            && bccomp($value, sprintf('%u', ip2long($this->range['max']))) <= 0;
    }

    /**
     * Checks whether IP address belongs to a subnet or not.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function assertSubnet(string $value): bool
    {
        $range = $this->range;
        $min = sprintf('%032b', ip2long($range['min']));
        $value = sprintf('%032b', ip2long($value));
        return ($value & $range['mask']) === ($min & $range['mask']);
    }
}
