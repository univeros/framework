<?php
namespace Altair\Http\Base;

use Altair\Http\Responder\CompoundResponder;

class Action
{
    /**
     * The domain specification.
     *
     * @var string class name of \Altair\Http\Contracts\DomainInterface
     */
    protected $domain;
    /**
     * The responder specification.
     *
     * @var string class name of \Altair\Http\Contracts\ResponderInterface
     */
    protected $responder;
    /**
     * The input specification.
     *
     * @var string class name of \Altair\Http\Contracts\InputInterface
     */
    protected $input;

    /**
     * @inheritDoc
     */
    public function __construct(
        $domain,
        $responder = null,
        $input = null
    ) {
        $this->domain = $domain;
        $this->responder = $responder?? CompoundResponder::class;
        $this->input = $input?? InputParser::class;
    }

    /**
     * Returns the domain specification fully qualified class name.
     *
     * @return string of \Altair\Http\Contracts\DomainInterface
     */
    public function getDomainClassName(): string
    {
        return $this->domain;
    }

    /**
     * Returns the responder specification fully qualified class name.
     *
     * @return string of \Altair\Http\Contracts\ResponderInterface
     */
    public function getResponderClassName(): string
    {
        return $this->responder;
    }

    /**
     * Returns the input specification fully qualified class name.
     *
     * @return string of \Altair\Http\Contracts\InputInterface
     */
    public function getInputClassName(): string
    {
        return $this->input;
    }
}
