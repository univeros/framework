<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\UrlRule;

class UrlRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            ["http://example.com"],
            ["https://example.com/path/to/file.php"],
            ["ftp://example.com/path/to/file.php/info"],
            ["news://example.com/path/to/file.php/info?foo=bar&baz=dib#zim"],
            ["gopher://example.com/?foo=bar&baz=dib#zim"],
            ["mms://user:pass@site.info/path/to/file.php/info?foo=bar&baz=dib#zim"],
        ];
    }

    public function falseProvider()
    {
        return [
            [[]],
            [''],
            [' '],
            ['example.com'],
            ['http://'],
            ["http://example.com\r/index.html"],
            ["http://example.com\n/index.html"],
            ["http://example.com\t/index.html"],
        ];
    }

    protected function buildRule()
    {
        return new UrlRule();
    }
}
