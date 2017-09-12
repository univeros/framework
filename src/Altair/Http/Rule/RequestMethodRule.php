<?php
namespace Altair\Http\Rule;

use Altair\Http\Contracts\HttpAuthRuleInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestMethodRule implements HttpAuthRuleInterface
{
    /**
     * @var array
     */
    protected $options = [
        'passthrough' => ['OPTIONS']
    ];

    /**
     * RequestMethodRule constructor.
     *
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        $this->options = array_merge($this->options, $options?? []);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request)
    {
        return !in_array($request->getMethod(), $this->options['passthrough']);
    }
}
