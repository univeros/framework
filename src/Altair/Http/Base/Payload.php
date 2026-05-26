<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Base;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Collection\SettingsCollection;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Structure\Map;

class Payload implements PayloadInterface
{
    /**
     * @var int
     */
    protected $status;

    protected InputCollection $inputCollection;

    /**
     * @var array
     */
    protected $output = [];

    /**
     * @var array
     */
    protected $messages = [];

    /**
     * @var Map|null
     */
    protected SettingsCollection $settingsCollection;

    /**
     * Payload constructor.
     *
     * @param InputCollection|null $inputCollection
     * @param SettingsCollection|null $settingsCollection
     */
    public function __construct(InputCollection $inputCollection = null, SettingsCollection $settingsCollection = null)
    {
        $this->inputCollection = $inputCollection?? new InputCollection();
        $this->settingsCollection = $settingsCollection?? new SettingsCollection();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withStatus(int $status): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->status = $status;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withInputCollection(InputCollection $inputCollection): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->inputCollection = $inputCollection;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getInputCollection(): InputCollection
    {
        return $this->inputCollection;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withOutput(array $output): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->output = $output;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withMessages(array $messages): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->messages = $messages;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withSetting(string $name, $value): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->settingsCollection->put($name, $value);

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withoutSetting(string $name): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->settingsCollection->remove($name);

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withSettingsCollection(SettingsCollection $settingsCollection): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->settingsCollection = $settingsCollection;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getSetting(string $name, $default = null)
    {
        return $this->settingsCollection->get($name);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getSettingsCollection(): SettingsCollection
    {
        return $this->settingsCollection;
    }
}
