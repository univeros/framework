<?php
namespace Altair\Tests\Sanitation;

use Altair\Container\Container;
use Altair\Sanitation\Contracts\SanitizableInterface;
use Altair\Sanitation\FiltersRunner;
use Altair\Sanitation\Resolver\FilterResolver;
use Altair\Sanitation\Sanitizer;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase
{
    public function testFilters()
    {
        $sanitizer = $this->getSanitizer();

        $entity = new SanitizableEntity();
        $entity->alpha = 'alp2ha';
        $entity->alphaNum = '=alphaNum3';
        $entity->between = 8;
        $entity->boolean = 'yes';
        $entity->callback = 'callback';
        $entity->datetime = '07/03/1971 03:00:01';
        $entity->datetimeFormatted = '07/03/1971 03:00:01';
        $entity->integer = '+123';
        $entity->lowercase = 'Hello World';
        $entity->lowercaseFirst = 'Hello World';
        $entity->max = 8;
        $entity->min = 2;
        $entity->maxStrLength = '12345678901';
        $entity->minStrLength = '123456';
        $entity->trim = ' hello world ';
        $entity->uppercase = 'hello world';
        $entity->uppercaseFirst = 'hello world';
        $entity->lowerCaseUpperCaseFirst = 'HELLo WoRlD';

        $sanitized = $sanitizer->sanitize($entity);
        $this->assertTrue($sanitized instanceof SanitizableInterface);
        $this->assertEquals('alpha', $sanitized->alpha);
        $this->assertEquals('alphaNum3', $sanitized->alphaNum);
        $this->assertEquals(6, $sanitized->between);
        $this->assertEquals('callback:callback', $sanitized->callback);
        $this->assertEquals('1971-07-03 03:00:01', $sanitized->datetime);
        $this->assertEquals('03/07/1971', $sanitized->datetimeFormatted);
        $this->assertEquals(123, $sanitized->integer);
        $this->assertEquals('hello world', $sanitized->lowercase);
        $this->assertEquals('hello World', $sanitized->lowercaseFirst);
        $this->assertEquals(5, $sanitized->max);
        $this->assertEquals(3, $sanitized->min);
        $this->assertEquals('1234567890', $sanitized->maxStrLength);
        $this->assertEquals('123456', $sanitized->minStrLength);
        $this->assertEquals('hello world', $sanitized->trim);
        $this->assertEquals('HELLO WORLD', $sanitized->uppercase);
        $this->assertEquals('Hello world', $sanitized->uppercaseFirst);
        $this->assertEquals('Hello world', $sanitized->lowerCaseUpperCaseFirst);


    }

    protected function getSanitizer()
    {
        return new Sanitizer(new FiltersRunner(new FilterResolver(new Container())));
    }


}
