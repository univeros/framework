<?php
namespace Altair\Tests\Sanitation;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Sanitation\Collection\FilterCollection;
use Altair\Sanitation\Contracts\SanitizableInterface;
use Altair\Sanitation\Filter\AbstractFilter;
use Altair\Sanitation\Filter\AlphaFilter;
use Altair\Sanitation\Filter\AlphaNumFilter;
use Altair\Sanitation\Filter\BetweenFilter;
use Altair\Sanitation\Filter\BooleanFilter;
use Altair\Sanitation\Filter\CallbackFilter;
use Altair\Sanitation\Filter\DateTimeFilter;
use Altair\Sanitation\Filter\IntegerFilter;
use Altair\Sanitation\Filter\LowerCaseFilter;
use Altair\Sanitation\Filter\MaxFilter;
use Altair\Sanitation\Filter\MaxStrLengthFilter;
use Altair\Sanitation\Filter\MinFilter;
use Altair\Sanitation\Filter\MinStrLengthFilter;
use Altair\Sanitation\Filter\TrimFilter;
use Altair\Sanitation\Filter\UpperCaseFilter;

class SanitizableEntity implements SanitizableInterface
{
    public $alpha;
    public $alphaNum;
    public $between;
    public $boolean;
    public $callback;
    public $datetime;
    public $datetimeFormatted;
    public $integer;
    public $lowercase;
    public $lowercaseFirst;
    public $max;
    public $min;
    public $maxStrLength;
    public $minStrLength;
    public $regex;
    public $trim;
    public $uppercase;
    public $uppercaseFirst;
    public $lowerCaseUpperCaseFirst;

    public function getFilters(): FilterCollection
    {
        return new FilterCollection(
            [
                'alpha' => AlphaFilter::class,
                'alphaNum' => AlphaNumFilter::class,
                'between' => [['class' => BetweenFilter::class, ':min' => 3, ':max' => 6]],
                'boolean' => BooleanFilter::class,
                'callback' => [
                    [
                        'class' => CallbackFilter::class,
                        ':callable' => function ($value) {
                            return $value . ':callback';
                        }
                    ]
                ],
                'datetime' => DateTimeFilter::class,
                'datetimeFormatted' => [['class' => DateTimeFilter::class, ':format' => 'd/m/Y']],
                'integer' => IntegerFilter::class,
                'lowercase' => LowerCaseFilter::class,
                'lowercaseFirst' => [['class' => LowerCaseFilter::class, ':firstOnly' => true]],
                'max' => [['class' => MaxFilter::class, ':max' => 5]],
                'min' => [['class' => MinFilter::class, ':min' => 3]],
                'maxStrLength' => [['class' => MaxStrLengthFilter::class, ':max' => 10]],
                'minStrLength' => [['class' => MinStrLengthFilter::class, ':min' => 5]],
                'trim' => TrimFilter::class,
                'uppercase' => UpperCaseFilter::class,
                'uppercaseFirst' => [['class' => UpperCaseFilter::class, ':firstOnly' => true]],
                'lowerCaseUpperCaseFirst' => [
                    LowerCaseFilter::class,
                    ['class' => UpperCaseFilter::class, ':firstOnly' => true]
                ]
            ]
        );
    }
}

class FilterA extends AbstractFilter
{
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        return parent::__invoke($payload->withAttribute(self::class, 'A executed'), $next);
    }

    public function parse($value)
    {
        return 'A:' . $value;
    }
}

class FilterB extends AbstractFilter
{
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        return parent::__invoke($payload->withAttribute(self::class, 'B executed'), $next);
    }

    public function parse($value)
    {
        return $value . ':B';
    }
}
