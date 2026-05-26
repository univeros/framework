<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Responder;

use Altair\Http\Contracts\OutputFormatterInterface;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Exception\InvalidFormatterException;
use Altair\Http\Formatter\JsonFormatter;
use Altair\Http\Traits\ResolverAwareTrait;
use Negotiation\AcceptEncoding;
use Negotiation\Negotiator;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FormattedResponder implements ResponderInterface
{
    use ResolverAwareTrait;

    protected array $formatters;

    /**
     * @param callable(string): object $resolver
     */
    public function __construct(
        protected Negotiator $negotiator,
        callable $resolver,
        array $formatters = [
            JsonFormatter::class => 1.0,
        ]
    ) {
        $this->resolver = $resolver;
        $this->formatters = $this->filterFormatters($formatters);
    }

    #[Override]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface {
        if ((bool) $payload->getOutput()) {
            return $this->format($request, $response, $payload);
        }

        return $response;
    }

    /**
     * Returns a copy of FormattedResponder adding the new formatter class
     */
    public function withFormatter(string $formatter, float $priority): FormattedResponder
    {
        $cloned = clone $this;
        $cloned->formatters = $this->filterFormatters(array_merge($this->formatters, [$formatter => $priority]));
        return $cloned;
    }

    protected function filterFormatters(array $formatters): array
    {
        $filtered = [];
        foreach ($formatters as $formatter => $quality) {
            if (!is_subclass_of($formatter, OutputFormatterInterface::class)) {
                throw new InvalidFormatterException(\sprintf("Invalid output formatter class '%s''", $formatter));
            }

            if (!\is_float($quality)) {
                throw new InvalidFormatterException(\sprintf("'%s' requires a quality float number.", $formatter));
            }

            $filtered[$formatter] = $quality;
        }

        return $filtered;
    }

    /**
     * Retrieve a map of accepted priorities with the responsible formatter.
     */
    protected function priorities(): array
    {
        $priorities = [];

        foreach ($this->formatters as $formatter => $quality) {
            foreach (\call_user_func([$formatter, 'accepts']) as $type) {
                $priorities[$type] = $formatter;
            }
        }

        return $priorities;
    }

    /**
     * Formats the response with selected formatter
     *
     *
     */
    protected function format(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface {
        $formatter = $this->getFormatter($request);
        $response = $response->withHeader('Content-Type', $formatter->type());
        // Overwrite the body instead of making a copy and dealing with the stream.
        $response->getBody()->write($formatter->body($payload));

        return $response;
    }

    /**
     * Retrieve the formatter to use for the current request.
     *
     * Uses content negotiation to find the best available output format for
     * the requested content type.
     *
     *
     */
    protected function getFormatter(ServerRequestInterface $request): object
    {
        $accept = $request->getHeaderLine('Accept');
        $priorities = $this->priorities();
        if (!empty($accept)) {
            $preferred = $this->negotiator->getBest($accept, array_keys($priorities));
        }

        if (!empty($preferred) && $preferred instanceof AcceptEncoding) {
            $formatter = $priorities[$preferred->getValue()];
        } else {
            $formatter = array_shift($priorities);
        }

        return $this->resolve($formatter);
    }
}
