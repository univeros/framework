<?php
namespace Altair\Validation\Contracts;

interface ValidatorInterface
{
    /**
     * Validates an input against the collection of rules the validator has.
     *
     * @param ValidatableInterface $validatable
     *
     * @return bool
     */
    public function validate(ValidatableInterface $validatable): bool;

    /**
     * Returns the payload that went throughout the queue of validation rules.
     *
     * @return PayloadInterface|null
     */
    public function getPayload(): ?PayloadInterface;
}
