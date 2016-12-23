<?php
namespace Altair\Http\Formatter;

use Altair\Http\Contracts\OutputFormatterInterface;
use Altair\Http\Contracts\PayloadInterface;

class JsonFormatter implements OutputFormatterInterface
{
    /**
     * @var int
     */
    protected $options = 0;
    /**
     * @var int
     */
    protected $depth = 512;

    /**
     * JsonFormatter constructor.
     *
     * @param int $options
     * @param int $depth
     */
    public function __construct(int $options = 0, int $depth = 512)
    {
        $this->options = 0;
        $this->depth = 512;
    }

    /**
     * @inheritdoc
     */
    public static function accepts(): array
    {
        return ['application/json'];
    }

    /**
     * @inheritdoc
     */
    public function type(): string
    {
        return 'application/json';
    }

    /**
     * @inheritdoc
     */
    public function body(PayloadInterface $payload): string
    {
        return json_encode($payload->getOutput(), $this->options, $this->depth);
    }
}
