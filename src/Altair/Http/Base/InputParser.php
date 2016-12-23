<?php
namespace Altair\Http\Base;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\InputInterface;
use JsonSerializable;
use Psr\Http\Message\ServerRequestInterface;

class InputParser implements InputInterface
{
    /**
     * @var InputCollection
     */
    protected $inputCollection;

    /**
     * InputParser constructor.
     *
     * @param InputCollection $inputCollection
     */
    public function __construct(InputCollection $inputCollection)
    {
        $this->inputCollection = $inputCollection;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return InputCollection
     */
    public function __invoke(ServerRequestInterface $request): InputCollection
    {
        $this->inputCollection->putAll(
            array_replace(
                $request->getAttributes(),
                $this->getParsedBody($request),
                $request->getCookieParams(),
                $request->getQueryParams(),
                $request->getUploadedFiles()
            )
        );

        return $this->inputCollection;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return array
     */
    protected function getParsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        if (empty($body)) {
            return [];
        }

        return is_object($body) && $body instanceof JsonSerializable
            ? $body->jsonSerialize()
            // if parsed body is an object but doesn't implements JsonSerializable use json parsing instead
            : is_object($body) ? json_decode(json_encode($body), true) : $body;
    }
}
