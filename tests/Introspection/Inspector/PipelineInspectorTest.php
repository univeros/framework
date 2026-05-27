<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Http\Collection\MiddlewareCollection;
use Altair\Introspection\Inspector\PipelineInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PipelineInspector::class)]
class PipelineInspectorTest extends TestCase
{
    public function testInspectAllReportsPipelineInOrder(): void
    {
        $queue = new MiddlewareCollection();
        $queue->push('App\\Middleware\\Cors');
        $queue->push('App\\Middleware\\Auth');
        $queue->push('App\\Middleware\\Action');

        $table = (new PipelineInspector($queue, 'default'))->inspectAll();

        $positions = array_column($table->rows, 'position');
        $middlewares = array_column($table->rows, 'middleware');

        $this->assertSame([0, 1, 2], $positions);
        $this->assertSame(['App\\Middleware\\Cors', 'App\\Middleware\\Auth', 'App\\Middleware\\Action'], $middlewares);
        $this->assertSame('default', $table->extras['pipeline']);
    }

    public function testEmptyPipelineRendersWithoutRows(): void
    {
        $table = (new PipelineInspector(new MiddlewareCollection()))->inspectAll();
        $this->assertSame([], $table->rows);
    }
}
