<?php
namespace Altair\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface HttpAuthRuleInterface
{
    public function __invoke(ServerRequestInterface $request);
}
