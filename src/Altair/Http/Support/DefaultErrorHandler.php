<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\RuntimeException;
use GdImage;
use Laminas\Diactoros\Response;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DefaultErrorHandler implements ErrorHandlerInterface
{
    /**
     * @var array<string, list<string>>
     */
    protected $handlers = [
        'plain' => [
            'text/plain',
            'text/css',
            'text/javascript',
        ],
        'jpeg' => [
            'image/jpeg',
        ],
        'gif' => [
            'image/gif',
        ],
        'png' => [
            'image/png',
        ],
        'svg' => [
            'image/svg+xml',
        ],
        'json' => [
            'application/json',
        ],
        'xml' => [
            'text/xml',
        ],
    ];

    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $error = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION);
        $accept = (new MimeType())->getFromResponseHeaderLine($response);
        $message = $error !== null ? $error->getMessage() : '';
        $response = (new Response('php://memory', $response->getStatusCode()));

        foreach ($this->handlers as $handler => $types) {
            foreach ($types as $type) {
                if (stripos($accept, $type) !== false) {
                    $this->{$handler}($response->getStatusCode(), $message);

                    return $response->withHeader('Content-Type', $type);
                }
            }
        }

        $this->html($response->getStatusCode(), $message);

        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Output the error as plain text.
     */
    protected function plain(int $statusCode, string $message): void
    {
        echo \sprintf('Error %d', $statusCode);

        if ($message !== '') {
            echo "\n" . $message;
        }
    }

    /**
     * Output the error as svg image.
     */
    protected function svg(int $statusCode, string $message): void
    {
        echo <<<EOT
            <svg xmlns="http://www.w3.org/2000/svg" width="200" height="50" viewBox="0 0 200 50">
                <text x="20" y="30" font-family="sans-serif" title="{$message}">
                    Error {$statusCode}
                </text>
            </svg>
            EOT;
    }

    /**
     * Output the error as html.
     */
    protected function html(int $statusCode, string $message): void
    {
        echo <<<EOT
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Error {$statusCode}</title>
                <style>html{font-family: sans-serif;}</style>
                <meta name="viewport" content="width=device-width, initial-scale=1">
            </head>
            <body>
                <h1>Error {$statusCode}</h1>
                {$message}
            </body>
            </html>
            EOT;
    }

    /**
     * Output the error as json.
     */
    protected function json(int $statusCode, string $message): void
    {
        $output = ['error' => $statusCode];
        if ($message !== '' && $message !== '0') {
            $output['message'] = $message;
        }

        echo json_encode($output);
    }

    /**
     * Output the error as xml.
     */
    protected function xml(int $statusCode, string $message): void
    {
        echo <<<EOT
            <?xml version="1.0" encoding="UTF-8"?>
            <error>
                <code>{$statusCode}</code>
                <message>{$message}</message>
            </error>
            EOT;
    }

    /**
     * Output the error as jpeg.
     */
    protected function jpeg(int $statusCode, string $message): void
    {
        $image = $this->createImage($statusCode, $message);
        imagejpeg($image);
    }

    /**
     * Output the error as gif.
     */
    protected function gif(int $statusCode, string $message): void
    {
        $image = $this->createImage($statusCode, $message);
        imagegif($image);
    }

    /**
     * Output the error as png.
     */
    protected function png(int $statusCode, string $message): void
    {
        $image = $this->createImage($statusCode, $message);
        imagepng($image);
    }

    /**
     * Creates a image resource with the error text.
     *
     * @throws RuntimeException when the GD image cannot be allocated
     */
    protected function createImage(int $statusCode, string $message): GdImage
    {
        $size = 200;
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            throw new RuntimeException('Unable to allocate a GD image for the error output.');
        }

        $textColor = imagecolorallocate($image, 255, 255, 255);

        if ($textColor === false) {
            throw new RuntimeException('Unable to allocate a color for the error image.');
        }

        imagestring($image, 5, 10, 10, 'Error ' . $statusCode, $textColor);
        foreach (str_split($message, \intval($size / 10)) as $line => $text) {
            imagestring($image, 5, 10, ($line * 18) + 28, $text, $textColor);
        }

        return $image;
    }
}
