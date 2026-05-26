<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec\Fixtures\SamplePackage\Middleware;

abstract class AttributeMiddleware
{
    public const string ATTRIBUTE_CLIENT_ID = 'sample:client-id';

    public const string ATTRIBUTE_LOCALE = 'sample:locale';
}
