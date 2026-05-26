<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     *
     */
    public function withStatus(int $status): PayloadInterface;

    /**
     * Get the status of the payload.
     *
     * @see StatusInterface
     *
     * @return int
     */
    public function getStatus(): ?int;

    /**
     * Create a copy of the payload with input collection map.
     *
     *
     */
    public function withInputCollection(InputCollection $inputCollection): PayloadInterface;

    /**
     * Get input array from the payload.
     */
    public function getInputCollection(): InputCollection;

    /**
     * Create a copy of the payload with output array.
     *
     *
     */
    public function withOutput(array $output): PayloadInterface;

    /**
     * Get output array from the payload.
     */
    public function getOutput(): array;

    /**
     * Create a copy of the payload with messages array.
     *
     *
     */
    public function withMessages(array $messages): PayloadInterface;

    /**
     * Get messages array from the payload.
     */
    public function getMessages(): array;

    /**
     * Create a copy of the payload with a modified setting.
     *
     *
     */
    public function withSetting(string $name, mixed $value): PayloadInterface;

    /**
     * Create a copy of the payload without a setting.
     *
     *
     */
    public function withoutSetting(string $name): PayloadInterface;

    /**
     * Create a copy of the payload with settings collection map.
     *
     *
     */
    public function withSettingsCollection(SettingsCollection $settingsCollection): PayloadInterface;

    /**
     * Get a payload setting. Default if not found.
     *
     *
     * @return mixed
     */
    public function getSetting(string $name, mixed $default = null);

    /**
     * Get all payload settings.
     */
    public function getSettingsCollection(): SettingsCollection;
}
