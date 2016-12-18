<?php
namespace Altair\Container\Contracts;

interface ExecutableBuilderInterface extends BuilderInterface
{
    /**
     * Checks whether callable or method string is executable
     *
     * @param mixed $executable
     *
     * @return bool
     */
    public function isExecutable($executable): bool;
}
