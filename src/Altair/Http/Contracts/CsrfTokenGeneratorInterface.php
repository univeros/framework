<?php
namespace Altair\Http\Contracts;


use Psr\Http\Message\ServerRequestInterface;

interface CsrfTokenGeneratorInterface
{
    public function generate(string $uri, ServerRequestInterface $request): array;
}