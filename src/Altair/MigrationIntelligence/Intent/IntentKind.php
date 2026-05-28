<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

enum IntentKind: string
{
    case AddColumn = 'add_column';
    case DropColumn = 'drop_column';
    case RenameColumn = 'rename_column';
    case ChangeColumn = 'change_column';
    case AddIndex = 'add_index';
    case DropIndex = 'drop_index';
    case AddForeignKey = 'add_foreign_key';
    case DataMigration = 'data_migration';
}
