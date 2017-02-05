<?php
namespace Altair\Validation;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Contracts\ValidatableInterface;
use Altair\Validation\Contracts\ValidatorInterface;

class Validator implements ValidatorInterface
{
    /**
     * @var RulesRunner
     */
    protected $runner;
    /**
     * @var
     */
    protected $payload;

    /**
     * Validator constructor.
     *
     * @param RulesRunner $runner
     */
    public function __construct(RulesRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param ValidatableInterface $validatable
     *
     * @return bool
     */
    public function validate(ValidatableInterface $validatable): bool
    {
        $this->payload = $this->buildPayload($validatable);

        foreach ($validatable->getRules() as $key => $value) {
            $keys = explode(',', $this->sanitize($key));
            foreach ($keys as $attribute) {
                $rules = is_array($value) ? $value : [$value];
                $runner = $this->runner->withRules($rules);
                $payload = $this->payload->withAttribute(PayloadInterface::ATTRIBUTE_KEY, $attribute);

                $this->payload = call_user_func($runner, $payload);
            }
        }

        return $this->payload->getAttribute(PayloadInterface::RESULT_KEY) === true;
    }

    /**
     * @inheritdoc
     */
    public function getPayload(): ?PayloadInterface
    {
        return $this->payload;
    }

    /**
     * @param ValidatableInterface $validatable
     *
     * @return PayloadInterface
     */
    protected function buildPayload(ValidatableInterface $validatable): PayloadInterface
    {
        $attributes = [
            PayloadInterface::SUBJECT_KEY => $validatable
        ];

        foreach ($validatable->getRules()->keys() as $key) {
            $keys = explode(',', $this->sanitize($key));
            foreach ($keys as $attribute) {
                $attributes[$attribute] = $validatable->$attribute;
            }
        }

        return new Payload($attributes);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function sanitize(string $value): string
    {
        return preg_replace('/\s+/', '', $value);
    }
}
