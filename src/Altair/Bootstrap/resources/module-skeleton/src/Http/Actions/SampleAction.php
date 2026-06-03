<?php

declare(strict_types=1);

namespace VendorModule\Http\Actions;

use Altair\Http\Base\Action;
use VendorModule\Domain\SampleService;
use VendorModule\Http\Inputs\SampleInput;
use VendorModule\Http\Responders\SampleResponder;

/**
 * Wires GET /sample to its domain, input DTO and responder.
 */
final class SampleAction extends Action
{
    public function __construct()
    {
        parent::__construct(
            domain: SampleService::class,
            responder: SampleResponder::class,
            input: SampleInput::class,
        );
    }
}
