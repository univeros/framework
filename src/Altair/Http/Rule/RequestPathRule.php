<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Rule;

use Altair\Http\Contracts\HttpAuthRuleInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

class RequestPathRule implements HttpAuthRuleInterface
{
    /**
     * @var array<string, list<string>>
     */
    protected array $options = [
        'path' => ['/'],
        'passthrough' => [],
    ];

    /**
     * Create a new rule instance
     *
     * @param array<string, list<string>> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ServerRequestInterface $request): bool
    {
        $uri = '/' . $request->getUri()->getPath();
        $uri = preg_replace('#/+#', '/', $uri);

        foreach ((array) $this->options['passthrough'] as $passthrough) {
            $passthrough = rtrim((string) $passthrough, '/');
            if ((bool) preg_match(\sprintf('@^%s(/.*)?$@', $passthrough), (string) $uri)) {
                return false;
            }
        }

        foreach ((array) $this->options['path'] as $path) {
            $path = rtrim((string) $path, '/');
            if ((bool) preg_match(\sprintf('@^%s(/.*)?$@', $path), (string) $uri)) {
                return true;
            }
        }

        return false;
    }
}
