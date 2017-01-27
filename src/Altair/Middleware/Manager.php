<?php
namespace Altair\Middleware;


use Altair\Middleware\Contracts\PayloadInterface;

class Manager
{
    /**
     * @var Runner
     */
    protected $runner;

    /**
     * Manager constructor.
     *
     * @param Runner $runner
     */
    public function __construct(Runner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    public function __invoke(PayloadInterface $payload)
    {
        $runner = $this->runner;

        return $runner($payload);
    }
}
