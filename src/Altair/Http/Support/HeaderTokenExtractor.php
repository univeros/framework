<?php

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenExtractorInterface;
use Psr\Http\Message\ServerRequestInterface;

class HeaderTokenExtractor implements TokenExtractorInterface
{
    /**
     * @var string
     */
    protected $header;

    /**
     * HeaderTokenParser constructor.
     *
     * @param string $header
     */
    public function __construct(string $header)
    {
        $this->header = $header;
    }

    /**
     * @inheritdoc
     */
    public function extract(ServerRequestInterface $request): ?string
    {
        $token = current($request->getHeader($this->header));

        return $token ?: null;
    }
}
