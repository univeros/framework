<?php
namespace Altair\Http\Contracts;

use Altair\Http\Collection\InputCollection;
use Psr\Http\Message\ServerRequestInterface;

interface InputInterface
{
    /**
     * Extract domain input from the request.
     *
     * @param  ServerRequestInterface $request
     *
     * @return InputCollection
     */
    public function __invoke(ServerRequestInterface $request): InputCollection;
}
