<?php
namespace Altair\Configuration\Traits;

use Altair\Configuration\Support\Env;

trait EnvTrait
{
    /**
     * @var Env
     */
    protected $env;

    /**
     * @param Env $env
     */
    public function __construct(Env $env)
    {
        $this->env = $env;
    }
}
