<?php
namespace Altair\Http\Collection;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\HttpStatusInterface;
use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Exception\OutOfBoundsException;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use ReflectionClass;
use Traversable;

class HttpStatusCollection implements Countable, IteratorAggregate
{
    /**
     * @var array of status codes and texts
     */
    protected $values;

    /**
     * @inheritdoc
     */
    public function __construct($values = [])
    {
        $this->values = $this->buildCommonValues();

        $this->mergeAll($values);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->values);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->values);
    }

    /**
     * Get the code for a given status text.
     *
     * @param string $reason the reason phrase of the http status
     *
     * @throws InvalidArgumentException If the requested $statusText is not valid
     * @throws OutOfBoundsException     If not status code is found
     *
     * @return int|null
     */
    public function getStatusCode($reason)
    {
        $reason = $this->filterReasonPhrase($reason);
        $code = $this->fetchCode($reason);
        if ($code !== false) {
            return $code;
        }
        throw new OutOfBoundsException(sprintf("No Http status code is associated to '%s'", $code));
    }

    /**
     * Get the text for a given status code.
     *
     * @param int $code
     *
     * @throws InvalidArgumentException If the requested $statusCode is not valid
     * @throws OutOfBoundsException     If the requested $statusCode is not found
     *
     * @return string
     */
    public function getReasonPhrase(int $code): string
    {
        $code = $this->filterCode($code);

        if (!isset($this->values[$code])) {
            throw new OutOfBoundsException(sprintf("Unknown http status code: '%s'", $code));
        }

        return $this->values[$code];
    }

    /**
     * Determines the response class of a response code.
     *
     * See the `CLASS_` constants for possible return values
     *
     * @param int $code
     *
     * @throws InvalidArgumentException If the requested $statusCode is not valid
     *
     * @return int
     */
    public function getResponseClass(int $code)
    {
        $responseClass = [
            1 => HttpStatusCodeInterface::RESPONSE_CLASS_INFORMATIONAL,
            2 => HttpStatusCodeInterface::RESPONSE_CLASS_SUCCESS,
            3 => HttpStatusCodeInterface::RESPONSE_CLASS_REDIRECTION,
            4 => HttpStatusCodeInterface::RESPONSE_CLASS_CLIENT_ERROR,
            5 => HttpStatusCodeInterface::RESPONSE_CLASS_SERVER_ERROR,
        ];
        $code = $this->filterCode($code);
        return $responseClass[(int) substr($code, 0, 1)];
    }

    /**
     * Checks whether a http code exists in the collection.
     *
     * @param int $code
     *
     * @return bool
     */
    public function hasCode(int $code): bool
    {
        try {
            $code = $this->filterCode($code);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return isset($this->values[$code]);
    }

    /**
     * Checks whether a reason phrase exists in the collection.
     *
     * @param string $reason
     *
     * @return bool
     */
    public function hasReasonPhrase(string $reason): bool
    {
        try {
            $reason = $this->filterReasonPhrase($reason);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return (bool)$this->fetchCode($reason);
    }

    /**
     * Adds or updates an item into the http status array.
     *
     * @param int $code
     * @param string $reason
     */
    public function merge(int $code, string $reason)
    {
        $code = $this->filterCode($code);
        $reason = $this->filterReasonPhrase($reason);
        $internalCode = $this->fetchCode($reason);
        if ((bool)$internalCode && $internalCode !== $code) {
            throw new InvalidArgumentException(
                "The reason phrase injected is already present in the default values."
            );
        }
        $this->values[$code] = $reason;
    }

    /**
     * Merges an array of status codes and its reason phrase into the default values.
     *
     * @param array|Traversable $values
     */
    public function mergeAll($values)
    {
        if (!is_array($values) || !$values instanceof Traversable) {
            throw new InvalidArgumentException("Values must be a Traversable object or an array");
        }
        foreach ($values as $code => $reason) {
            $this->merge($code, $reason);
        }
    }

    /**
     * Initializes default http status codes and messages.
     *
     * @see HttpStatusInterface
     * @see HttpStatusCodeInterface
     *
     * @return array
     */
    protected function buildCommonValues(): array
    {
        $values = [];
        $reflectionClass = new ReflectionClass(HttpStatusInterface::class);
        foreach ($reflectionClass->getConstants() as $name => $value) {
            $code = constant('Altair\Http\Contracts\HttpStatusCodeInterface::' . $name);
            $values[$code] = $value;
        }

        return $values;
    }

    /**
     * Fetch the status code for a given reason phrase.
     *
     * @param string $text the reason phrase
     *
     * @return int|null
     */
    protected function fetchCode($text):? int
    {
        $code = array_search(strtolower($text), array_map('strtolower', $this->values));

        return $code === false ? null : $code;
    }

    /**
     * @param string $reason
     *
     * @return string
     */
    protected function filterReasonPhrase(string $reason): string
    {
        $reason = trim($reason);
        if (preg_match(',[\r\n],', $reason)) {
            throw new InvalidArgumentException('The reason phrase can not contain carriage return characters');
        }

        return $reason;
    }

    /**
     * Filters the status code
     *
     * @param int $code
     *
     * @return int
     */
    protected function filterCode(int $code): int
    {
        $filtered = filter_var(
            $code,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => HttpStatusCodeInterface::HTTP_MIN_RANGE,
                    'max_range' => HttpStatusCodeInterface::HTTP_MAX_RANGE
                ]
            ]
        );

        if (!$filtered) {
            throw new InvalidArgumentException(
                sprintf(
                    "A status code must be positive integer between '%s' and '%s', '%s' given.",
                    HttpStatusCodeInterface::HTTP_MIN_RANGE,
                    HttpStatusCodeInterface::HTTP_MAX_RANGE,
                    $code
                )
            );
        }

        return $filtered;
    }
}
