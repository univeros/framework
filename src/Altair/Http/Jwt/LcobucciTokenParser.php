<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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



    /**
     * @var Validator
     */
    protected $validator;

    public function __construct(protected ServerRequestInterface $request, protected Parser $parser, protected TokenConfigurationInterface $config)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function parse(string $token): TokenInterface
    {
        /** @var Plain $parsed */
        $parsed = $this->parseToken($token);
        $this->verifySignature($parsed, $token);

        return new Token($token, $parsed->claims()->all());
    }

    /**
     * @throws InvalidTokenException
     */
    protected function parseToken(string $token): LcobucciToken
    {
        try {
            return $this->parser->parse($token);
        } catch (InvalidArgumentException) {
            throw new InvalidTokenException(sprintf('Count not parse authorization token "%s"', $token));
        }
    }

    /**
     *
     * @throws InvalidTokenException
     */
    protected function verifySignature(Plain $token, string $jwt)
    {
        $key = new Key($this->config->getPublicKey());

        if (!$this->config->getSigner()->verify($token->signature()->hash(), $token->payload(), $key)) {
            throw new InvalidTokenException(
                sprintf('Provided authorization token %s is invalid.', $jwt)
            );
        }
    }

    /**
     * @throws InvalidTokenException
     */
    protected function validateToken(Plain $token)
    {
        try {
            $this->validator->assert($token, new IssuedBy($this->request->getUri()), new ValidAt(new SystemClock()));
        } catch (LcobucciInvalidTokenException $lcobucciInvalidTokenException) {
            throw new InvalidTokenException($lcobucciInvalidTokenException->getMessage());
        }
    }
}
