<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenExtractorInterface;
use Psr\Http\Message\ServerRequestInterface;

class HeaderTokenExtractor implements TokenExtractorInterface
{
    /**
     * @var string
     */
    protected $header;

    /**
     * HeaderTokenParser constructor.
     *
     * @param string $header
     */
    public function __construct(string $header)
    {
        $this->header = $header;
    }

    /**
     * @inheritDoc
     */
    public function extract(ServerRequestInterface $request): ?string
    {
        $token = current($request->getHeader($this->header));

        return $token ?: null;
    }
}
