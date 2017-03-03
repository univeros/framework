<?php
namespace Altair\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface FormatNegotiatorInterface
{
    const DEFAULT_FORMAT = 'html';

    /**
     * Returns the format from request attribute.
     *
     * @param ServerRequestInterface $request
     *
     * @return null|string
     */
    public function getFromServerRequestAttribute(ServerRequestInterface $request): ?string;

    /**
     * Returns the format from the file extension in uri path (ie .html).
     *
     * @param ServerRequestInterface $request
     *
     * @return null|string
     */
    public function getFromServerRequestUriPath(ServerRequestInterface $request): ?string;

    /**
     * Returns format from server request headers.
     *
     * @param ServerRequestInterface $request
     *
     * @return null|string
     */
    public function getFromServerRequestHeaderLine(ServerRequestInterface $request): ?string;

    /**
     * Returns the content type based on the format. The format must exists on the map.
     *
     * @param string $format
     *
     * @return string
     */
    public function getContentTypeByFormat(string $format): string;
}
