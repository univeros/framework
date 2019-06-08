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

class QueryParamsTokenExtractor implements TokenExtractorInterface
{
    /**
     * @var string
     */
    protected $parameter;

    /**
     * @param string $parameter Name of the query string parameter
     */
    public function __construct($parameter)
    {
        $this->parameter = (string)$parameter;
    }

    /**
     * @inheritDoc
     */
    public function extract(ServerRequestInterface $request): ?string
    {
        $query = $request->getQueryParams();

        return $query[$this->parameter] ?? null;
    }
}
