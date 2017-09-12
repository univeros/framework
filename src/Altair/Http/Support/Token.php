<?php

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenInterface;

class Token implements TokenInterface
{
    /**
     * @var string
     */
    private $token;
    /**
     * @var array
     */
    private $metadata;

    /**
     * Token constructor.
     *
     * @param $token
     * @param array $metadata
     */
    public function __construct($token, array $metadata)
    {
        $this->token = $token;
        $this->metadata = $metadata;
    }

    /**
     * @inheritdoc
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(string $key = null)
    {
        return null !== $key
            ? $this->metadata[$key] ?? null
            : null;
    }
}
