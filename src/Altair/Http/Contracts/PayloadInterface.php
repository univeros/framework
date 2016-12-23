<?php
namespace Altair\Http\Contracts;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Collection\SettingsCollection;

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
    public function withStatus(string $status): PayloadInterface;

    /**
     * Get the status of the payload.
     *
     * @see StatusInterface
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Create a copy of the payload with input collection map.
     *
     * @param InputCollection $inputCollection
     *
     * @return PayloadInterface
     */
    public function withInputCollection(InputCollection $inputCollection): PayloadInterface;

    /**
     * Get input array from the payload.
     *
     * @return InputCollection
     */
    public function getInputCollection(): InputCollection;

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
    public function withSetting(string $name, $value): PayloadInterface;

    /**
     * Create a copy of the payload without a setting.
     *
     * @param string $name
     *
     * @return PayloadInterface
     */
    public function withoutSetting(string $name): PayloadInterface;

    /**
     * Create a copy of the payload with settings collection map.
     *
     * @param SettingsCollection $settingsCollection
     *
     * @return PayloadInterface
     */
    public function withSettingsCollection(SettingsCollection $settingsCollection): PayloadInterface;

    /**
     * Get a payload setting. Default if not found.
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function getSetting(string $name, $default = null);

    /**
     * Get all payload settings.
     *
     * @return SettingsCollection
     */
    public function getSettingsCollection(): SettingsCollection;
}
