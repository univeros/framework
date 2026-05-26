<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Linter;

enum DriftKind: string
{
    case MissingInputField = 'missing-input-field';
    case UnknownInputField = 'unknown-input-field';
    case MissingValidationRule = 'missing-validation-rule';
    case ResponderMissingStatus = 'responder-missing-status';
    case UnregisteredRoute = 'unregistered-route';
}
