<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Base;

use Altair\Http\Responder\CompoundResponder;

class Action
{
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
     * @param string $domain
     * @param string|null $responder class name of \Altair\Http\Contracts\ResponderInterface
     * @param string|null $input class name of \Altair\Http\Contracts\InputInterface
     */
    public function __construct(
        /**
         * The domain specification.
         *
         * @var string class name of \Altair\Http\Contracts\DomainInterface
         */
        protected $domain,
        $responder = null,
        $input = null
    ) {
        $this->responder = $responder ?? CompoundResponder::class;
        $this->input = $input ?? InputParser::class;
    }

    /**
     * Rehydrates an Action from a `var_export`-serialized state array.
     *
     * Required so that {@see \FastRoute\cachedDispatcher} can read back
     * Actions stored in its compiled route-cache file — without this,
     * the cache file would emit `Action::__set_state(...)` calls that
     * PHP could not resolve.
     *
     * @param array{domain: string, responder: string, input: string} $data
     */
    public static function __set_state(array $data): self
    {
        return new self($data['domain'], $data['responder'], $data['input']);
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
