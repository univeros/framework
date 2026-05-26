<?php

declare(strict_types=1);

namespace Altair\Tests\Common\Support;

use Altair\Common\Support\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{
    private Str $str;

    #[\Override]
    protected function setUp(): void
    {
        $this->str = new Str();
    }

    public function testByteLengthCountsBytes(): void
    {
        $this->assertSame(5, $this->str->byteLength('hello'));
        $this->assertSame(4, $this->str->byteLength("\xC3\xA9\xC3\xA9")); // 'éé' in UTF-8 is 4 bytes
    }

    public function testByteSubStringReturnsSlice(): void
    {
        $this->assertSame('ell', $this->str->byteSubString('hello', 1, 3));
        $this->assertSame('lo', $this->str->byteSubString('hello', 3));
    }

    public function testTruncateAppendsSuffixWhenOverLength(): void
    {
        $this->assertSame('hello...', $this->str->truncate('hello world', 5));
        $this->assertSame('short', $this->str->truncate('short', 10));
    }

    public function testTruncateWordsTruncatesByWordCount(): void
    {
        $this->assertSame('one two...', $this->str->truncateWords('one two three four', 2));
        $this->assertSame('one two', $this->str->truncateWords('one two', 5));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue($this->str->startsWith('hello world', 'hello'));
        $this->assertFalse($this->str->startsWith('hello world', 'world'));
        $this->assertTrue($this->str->startsWith('hello world', 'HELLO', caseSensitive: false));
        $this->assertTrue($this->str->startsWith('anything', '')); // empty needle is always a prefix
    }

    public function testEndsWith(): void
    {
        $this->assertTrue($this->str->endsWith('hello world', 'world'));
        $this->assertFalse($this->str->endsWith('hello world', 'hello'));
        $this->assertTrue($this->str->endsWith('hello world', 'WORLD', caseSensitive: false));
        $this->assertTrue($this->str->endsWith('anything', '')); // empty needle is always a suffix
        $this->assertFalse($this->str->endsWith('hi', 'hello')); // needle longer than haystack
    }

    public function testCountWords(): void
    {
        $this->assertSame(3, $this->str->countWords('one two three'));
        $this->assertSame(3, $this->str->countWords("one\t two  three"));
        $this->assertSame(0, $this->str->countWords(''));
    }

    public function testReplaceFirst(): void
    {
        $this->assertSame('Xello hello', $this->str->replaceFirst('hello', 'Xello', 'hello hello'));
        $this->assertSame('no match', $this->str->replaceFirst('xxx', 'yyy', 'no match'));
    }

    public function testReplaceLast(): void
    {
        $this->assertSame('hello Xello', $this->str->replaceLast('hello', 'Xello', 'hello hello'));
        $this->assertSame('no match', $this->str->replaceLast('xxx', 'yyy', 'no match'));
    }
}
