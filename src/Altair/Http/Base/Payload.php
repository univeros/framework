<?php
namespace Altair\Http\Base;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Collection\SettingsCollection;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Structure\Map;

class Payload implements PayloadInterface
{
    /**
     * @var string
     */
    protected $status;
    /**
     * @var InputCollection
     */
    protected $inputCollection;
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
    protected $settingsCollection;

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
     * @inheritdoc
     */
    public function withStatus(string $status): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->status = $status;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public function withInputCollection(InputCollection $inputCollection): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->inputCollection = $inputCollection;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function getInputCollection(): InputCollection
    {
        return $this->inputCollection;
    }

    /**
     * @inheritdoc
     */
    public function withOutput(array $output): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->output = $output;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * @inheritdoc
     */
    public function withMessages(array $messages): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->messages = $messages;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @inheritdoc
     */
    public function withSetting(string $name, $value): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->settingsCollection->put($name, $value);

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function withoutSetting(string $name): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->settingsCollection->remove($name);

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function withSettingsCollection(SettingsCollection $settingsCollection): PayloadInterface
    {
        $cloned = clone $this;
        $cloned->settingsCollection = $settingsCollection;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function getSetting(string $name, $default = null)
    {
        return $this->settingsCollection->get($name);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsCollection(): SettingsCollection
    {
        return $this->settingsCollection;
    }
}
