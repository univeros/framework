<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Hash;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Hashes the raw request body bytes for use as an idempotency
 * payload-fingerprint. SHA-256 by default; the algorithm is fixed
 * because cross-environment consistency matters more than
 * configurability.
 *
 * The hash is over the raw bytes, not parsed JSON — so semantically
 * equivalent representations (attribute reordering, whitespace
 * differences) produce different hashes. Applications that want
 * JSON-canonical hashing add an upstream middleware that canonicalises
 * the body first.
 *
 * The hasher rewinds the body stream after reading so downstream
 * middleware / handlers still see the same content from position 0.
 */
class RequestBodyHasher
{
    public function hash(ServerRequestInterface $request): string
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = (string) $body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return hash('sha256', $contents);
    }
}
