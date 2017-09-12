<?php

namespace Altair\Http\Jwt;

use Altair\Http\Contracts\TokenConfigurationInterface;
use Altair\Http\Contracts\TokenInterface;
use Altair\Http\Contracts\TokenParserInterface;
use Altair\Http\Exception\InvalidTokenException;
use Altair\Http\Support\Token;
use InvalidArgumentException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Token as LcobucciToken;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\ValidAt;
use Lcobucci\JWT\Validation\InvalidTokenException as LcobucciInvalidTokenException;
use Lcobucci\JWT\Validator;
use Psr\Http\Message\ServerRequestInterface;

class LcobucciTokenParser implements TokenParserInterface
{
    protected $request;
    protected $parser;
    protected $config;
    /**
     * @var Validator
     */
    protected $validator;

    public function __construct(
        ServerRequestInterface $request,
        Parser $parser,
        TokenConfigurationInterface $config,
        Validator $validator
    ) {
        $this->request = $request;
        $this->parser = $parser;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function parse(string $token): TokenInterface
    {
        /** @var Plain $parsed */
        $parsed = $this->parseToken($token);
        $this->verifySignature($parsed, $token);

        return new Token($token, $parsed->claims()->all());
    }

    /**
     * @param string $token
     *
     * @return \Lcobucci\JWT\Token
     * @throws InvalidTokenException
     */
    protected function parseToken(string $token): LcobucciToken
    {
        try {
            return $this->parser->parse($token);
        } catch (InvalidArgumentException $e) {
            throw new InvalidTokenException(sprintf('Count not parse authorization token "%s"', $token));
        }
    }

    /**
     * @param Plain $token
     * @param string $jwt
     *
     * @throws InvalidTokenException
     */
    protected function verifySignature(Plain $token, string $jwt)
    {
        $key = new Key($this->config->getPublicKey());

        if (!$this->config->getSigner()->verify($token->signature()->hash(), $token->payload(), $key)) {
            throw new InvalidTokenException(
                sprintf('Provided authorization token is invalid.', $jwt)
            );
        }
    }

    /**
     * @param Plain $token
     *
     * @throws InvalidTokenException
     */
    protected function validateToken(Plain $token)
    {
        try {
            $this->validator->assert($token, new IssuedBy($this->request->getUri()), new ValidAt(new SystemClock()));
        } catch (LcobucciInvalidTokenException $e) {
            throw new InvalidTokenException($e->getMessage());
        }
    }
}
