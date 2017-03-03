<?php
namespace Altair\Http\Traits;

use Altair\Http\Contracts\HttpAuthRuleInterface;
use Altair\Http\Contracts\IdentityValidatorInterface;
use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Exception\RuntimeException;
use Altair\Http\Support\RequestMethodRule;
use Psr\Http\Message\ServerRequestInterface;

trait HttpAuthenticationAwareTrait
{
    /**
     * @var IdentityValidatorInterface
     */
    protected $validator;
    /**
     * @var HttpAuthRuleInterface[]|array
     */
    protected $rules;
    /**
     * @var bool
     */
    protected $ssl;
    /**
     * @var array|string
     */
    protected $allowed;
    /**
     * @var string
     */
    protected $realm;
    /**
     * @var string Digest Authentication only attribute.
     */
    protected $nonce;
    /**
     * @var mixed|null
     */
    protected $onError;
    /**
     * @var mixed|string
     */
    protected $environment;

    /**
     * Authentication middleware Constructor.
     *
     * @param IdentityValidatorInterface $validator
     * @param HttpAuthRuleInterface[] $rules
     * @param array $options
     */
    public function __construct(IdentityValidatorInterface $validator, array $rules = null, array $options = null)
    {
        $this->validator = $validator;
        $this->rules = $rules?? [];
        if (empty($this->rules)) {
            $this->rules[] = new RequestMethodRule(); // OPTIONS by default
        } else {
            foreach ($this->rules as $rule) {
                if (!($rule instanceof HttpAuthRuleInterface)) {
                    throw new InvalidArgumentException(
                        sprintf('Rules must be of type "%s".', HttpAuthRuleInterface::class)
                    );
                }
            }
        }
        $this->realm = $options['realm']?? 'Login';
        $this->environment = $options['environment']?? 'HTTP_AUTHORIZATION';
        $this->ssl = $options['ssl']?? true;
        $this->allowed = $options['allowed']?? ['localhost', '127.0.0.1', '::1'];
        $this->onError = $options['onError']?? null;
        $this->nonce = $options['nonce']?? null;
    }

    /**
     * Checks whether the request should be authenticated by firing the rules. If one of them returns false, then
     * request should not be authenticated.
     *
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    protected function shouldAuthenticateRequest(ServerRequestInterface $request): bool
    {
        foreach ($this->rules as $rule) {
            if (false === $rule($request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether the request is over a secured channel. If not, makes sure the host is within one of our allowed
     * and if invalid, throws an error.
     *
     * @param string $host
     * @param string $scheme
     *
     * @throws RuntimeException to stop execution
     * @return void
     */
    protected function checkAllowance(string $host, string $scheme)
    {
        if ("https" !== $scheme && true === $this->ssl) {
            $allowed = is_string($this->allowed) ? explode(',', $this->allowed) : $this->allowed;
            if (!in_array($host, $allowed)) {
                $message = sprintf(
                    "Insecure (HTTP) use of middleware over %s is denied.",
                    strtoupper($scheme)
                );
                throw new RuntimeException($message);
            }
        }
    }
}
