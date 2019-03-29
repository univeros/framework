<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

use Exception;
use Psr\Http\Message\ResponseInterface;

class HttpMethodNotAllowedException extends HttpBadRequestException
{
    protected $allowed = [];

    public function __construct($allowed = [], $message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function withResponse(ResponseInterface $response)
    {
        if (!empty($this->allowed)) {
            $response = $response->withHeader('Allow', implode(',', $this->allowed));
        }

        return $response;
    }
}
