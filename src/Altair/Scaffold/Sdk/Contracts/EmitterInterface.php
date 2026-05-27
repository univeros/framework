<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Contracts;

use Altair\Scaffold\Sdk\EmittedSdk;
use Altair\Scaffold\Sdk\Model\OpenApiDocument;

/**
 * One target-language SDK emitter.
 *
 * Emitters are pure: same {@see OpenApiDocument} in → byte-identical
 * {@see EmittedSdk} out, so `--check` mode can diff regenerated content
 * against what's on disk for CI drift detection.
 */
interface EmitterInterface
{
    /**
     * Language identifier used on the CLI (`typescript`, `python`).
     */
    public function language(): string;

    /**
     * Default single-file output filename (`sdk.ts`, `client.py`).
     */
    public function defaultFileName(): string;

    public function emit(OpenApiDocument $document, bool $multiFile = false): EmittedSdk;
}
