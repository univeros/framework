<?php

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenExtractorInterface;
use Psr\Http\Message\ServerRequestInterface;

class QueryParamsTokenExtractor implements TokenExtractorInterface
{
    /**
     * @var string
     */
    protected $parameter;

    /**
     * @param string $parameter Name of the query string parameter
     */
    public function __construct($parameter)
    {
        $this->parameter = (string)$parameter;
    }

    /**
     * @inheritdoc
     */
    public function extract(ServerRequestInterface $request): ?string
    {
        $query = $request->getQueryParams();

        return $query[$this->parameter] ?? null;
    }
}
