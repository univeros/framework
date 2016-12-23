<?php
namespace Altair\Http\Contracts;

interface PayloadInterface extends HttpStatusInterface
{
    /**
     * Create a copy of the payload with the status.
     *
     * @see StatusInterface
     *
     * @param string $status
     *
     * @return PayloadInterface
     */
    public function withStatus($status): PayloadInterface;

    /**
     * Get the status of the payload.
     *
     * @see StatusInterface
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Create a copy of the payload with input array.
     *
     * @param array $input
     *
     * @return PayloadInterface
     */
    public function withInput(array $input): PayloadInterface;

    /**
     * Get input array from the payload.
     *
     * @return array
     */
    public function getInput(): array;

    /**
     * Create a copy of the payload with output array.
     *
     * @param array $output
     *
     * @return PayloadInterface
     */
    public function withOutput(array $output): PayloadInterface;

    /**
     * Get output array from the payload.
     *
     * @return array
     */
    public function getOutput(): array;

    /**
     * Create a copy of the payload with messages array.
     *
     * @param array $messages
     *
     * @return PayloadInterface
     */
    public function withMessages(array $messages): PayloadInterface;

    /**
     * Get messages array from the payload.
     *
     * @return array
     */
    public function getMessages(): array;

    /**
     * Create a copy of the payload with a modified setting.
     *
     * @param string $name
     * @param mixed $value
     *
     * @return PayloadInterface
     */
    public function withSetting($name, $value): PayloadInterface;

    /**
     * Create a copy of the payload without a setting.
     *
     * @param string $name
     *
     * @return PayloadInterface
     */
    public function withoutSetting($name): PayloadInterface;

    /**
     * Get a payload setting.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getSetting($name);

    /**
     * Get all payload settings.
     *
     * @return array
     */
    public function getSettings(): array;
}
