<?php
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
     * @inheritdoc
     */
    public function assert($value): bool
    {
        return $this->assertAddress($value) && $this->assertNetwork($value);
    }

    /**
     * @inheritdoc
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
            list($range['min'], $range['max']) = explode('-', $value);
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
     * @param string $input
     * @param array $range
     *
     * @return array
     */
    protected function parseRangeUsingWildcards(string $input, array $range): array
    {
        $input = $this->fillAddress($input);
        $range['min'] = strtr($input, '*', '0');
        $range['max'] = str_replace('*', '255', $input);

        return $range;
    }

    /**
     * @param string $input
     * @param array $range
     *
     * @return array
     */
    protected function parseRangeUsingCidr(string $input, array $range): array
    {
        $input = explode('/', $input);
        $this->fillAddress($input[0], '0');
        $range['min'] = $input[0];
        $isMask = mb_strpos($input[1], '.') !== false;
        if ($isMask && $this->assertAddress($input[1])) {
            $range['mask'] = sprintf('%032b', ip2long($input[1]));
            return $range;
        }

        if ($isMask || $input[1] < 8 || $input[1] > 30) {
            throw new InvalidArgumentException('Invalid network mask.');
        }

        $range['mask'] = sprintf('%032b', ip2long(long2ip(~(pow(2, (32 - $input[1])) - 1))));

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
