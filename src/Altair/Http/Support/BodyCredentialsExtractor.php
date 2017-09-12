<?php

namespace Altair\Http\Support;

use Altair\Http\Contracts\CredentialsExtractorInterface;
use Psr\Http\Message\ServerRequestInterface;

class BodyCredentialsExtractor implements CredentialsExtractorInterface
{
    /**
     * @var string
     */
    private $identifier;
    /**
     * @var string
     */
    private $password;

    /**
     * BodyCredentialsBuilder constructor.
     *
     * @param string $identifier
     * @param string $password
     */
    public function __construct($identifier = 'username', $password = 'password')
    {
        $this->identifier = $identifier;
        $this->password = $password;
    }

    /**
     * @inheritdoc
     */
    public function extract(ServerRequestInterface $request): ?array
    {
        $body = $request->getParsedBody();

        if (empty($body[$this->identifier]) || empty($body[$this->password])) {
            return null;
        }

        return [$this->identifier => $body[$this->identifier], $this->password => $body[$this->password]];
    }
}
