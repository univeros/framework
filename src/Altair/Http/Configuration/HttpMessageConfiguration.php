<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;

class HttpMessageConfiguration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function apply(Container $container): void
    {
        $container
            ->alias(
                RequestInterface::class,
                ServerRequest::class
            )
            ->alias(
                ServerRequestInterface::class,
                ServerRequest::class
            )
            ->alias(
                ResponseInterface::class,
                Response::class
            )
            ->delegate(
                ServerRequest::class,
                [ServerRequestFactory::class, 'fromGlobals']
            );
    }
}
