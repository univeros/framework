<?php

declare(strict_types=1);

namespace VendorModule\Database\Migrations;

use Cycle\Migrations\Migration;

/**
 * Creates the table behind {@see \VendorModule\Entity\SampleEntity}. Picked up
 * by `bin/altair db:migrate` in any host that registers this module — no host
 * configuration required.
 */
final class M20260101000000CreateModuleSamplesTable extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->table('module_samples')
            ->addColumn('id', 'primary', ['nullable' => false])
            ->addColumn('name', 'string', ['nullable' => false])
            ->create();
    }

    public function down(): void
    {
        $this->table('module_samples')->drop();
    }
}
