<?php
namespace Altair\Http\Responder;

use Altair\Http\Contracts\OutputFormatterInterface;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Exception\InvalidFormatterException;
use Altair\Http\Formatter\JsonFormatter;
use Altair\Http\Traits\ResolverAwareTrait;
use Negotiation\AcceptEncoding;
use Negotiation\Negotiator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\ResolverInterface;

class FormattedResponder implements ResponderInterface
{
    use ResolverAwareTrait;

    /**
     * @var Negotiator
     */
    protected $negotiator;
    /**
     * @var array
     */
    protected $formatters;

    /**
     * FormattedResponder constructor.
     *
     * @param Negotiator $negotiator
     * @param ResolverInterface $resolver
     * @param array $formatters
     */
    public function __construct(
        Negotiator $negotiator,
        ResolverInterface $resolver,
        array $formatters = [
            JsonFormatter::class => 1.0
        ]
    ) {
        $this->negotiator = $negotiator;
        $this->resolver = $resolver;
        $this->formatters = $this->filterFormatters($formatters);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param PayloadInterface $payload
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface {
        if ((bool)$payload->getOutput()) {
            $response = $this->format($request, $response, $payload);
        }

        return $response;
    }

    /**
     * @param array $formatters
     *
     * @return array
     */
    protected function filterFormatters(array $formatters): array
    {
        $filtered = [];
        foreach ($formatters as $formatter => $quality) {
            if (!is_subclass_of($formatter, OutputFormatterInterface::class)) {
                throw new InvalidFormatterException("Invalid output formatter class '{$formatter}''");
            }

            if (!is_float($quality)) {
                throw new InvalidFormatterException("'{$formatter}' requires a quality float number.");
            }
            $this->formatters[$formatter] = $quality;
        }

        return $filtered;
    }

    /**
     * Retrieve a map of accepted priorities with the responsible formatter.
     *
     * @return array
     */
    protected function priorities(): array
    {
        $priorities = [];

        foreach ($this->formatters as $formatter => $quality) {
            foreach (call_user_func([$formatter, 'accepts']) as $type) {
                $priorities[$type] = $formatter;
            }
        }

        return $priorities;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param PayloadInterface $payload
     *
     * @return ResponseInterface
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
     * @param ServerRequestInterface $request
     *
     * @return OutputFormatterInterface|object
     */
    protected function getFormatter(ServerRequestInterface $request)
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
