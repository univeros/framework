<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Rule;

use Altair\Http\Contracts\HttpAuthRuleInterface;
use Psr\Http\Message\ServerRequestInterface;

class RequestPathRule implements HttpAuthRuleInterface
{
    /**
     * @var array
     */
    protected $options = [
        'path' => ['/'],
        'passthrough' => []
    ];

    /**
     * Create a new rule instance
     *
     * @param string[] $options
     *
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $uri = '/' . $request->getUri()->getPath();
        $uri = preg_replace('#/+#', '/', $uri);

        foreach ((array)$this->options['passthrough'] as $passthrough) {
            $passthrough = rtrim($passthrough, '/');
            if ((bool)preg_match("@^{$passthrough}(/.*)?$@", $uri)) {
                return false;
            }
        }

        foreach ((array)$this->options['path'] as $path) {
            $path = rtrim($path, '/');
            if ((bool)preg_match("@^{$path}(/.*)?$@", $uri)) {
                return true;
            }
        }

        return false;
    }
}
