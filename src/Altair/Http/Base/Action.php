<?php
namespace Altair\Http\Base;

use Altair\Http\Responder\CompoundResponder;

class Action
{
    /**
     * The domain specification.
     *
     * @var \Altair\Http\Contracts\DomainInterface
     */
    protected $domain;
    /**
     * The responder specification.
     *
     * @var \Altair\Http\Contracts\ResponderInterface
     */
    protected $responder;
    /**
     * The input specification.
     *
     * @var \Altair\Http\Contracts\InputInterface
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
     * Returns the domain specification.
     *
     * @return \Altair\Http\Contracts\DomainInterface
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Returns the responder specification.
     *
     * @return \Altair\Http\Contracts\ResponderInterface
     */
    public function getResponder()
    {
        return $this->responder;
    }

    /**
     * Returns the input specification.
     *
     * @return \Altair\Http\Contracts\InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }
}
