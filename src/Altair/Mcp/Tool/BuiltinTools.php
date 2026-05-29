<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool;

use Altair\Mcp\Tool\Database\DbMigrateTool;
use Altair\Mcp\Tool\Database\DbQueryTool;
use Altair\Mcp\Tool\Database\DbSchemaTool;
use Altair\Mcp\Tool\Database\PlanMigrationTool;
use Altair\Mcp\Tool\Discovery\ContainerResolveTool;
use Altair\Mcp\Tool\Discovery\DescribeEndpointTool;
use Altair\Mcp\Tool\Discovery\DescribePackageTool;
use Altair\Mcp\Tool\Discovery\ListCommandsTool;
use Altair\Mcp\Tool\Discovery\ListEndpointsTool;
use Altair\Mcp\Tool\Discovery\ListPackagesTool;
use Altair\Mcp\Tool\Discovery\ListSpecsTool;
use Altair\Mcp\Tool\Discovery\ReadSpecTool;
use Altair\Mcp\Tool\Eval\EvalTool;
use Altair\Mcp\Tool\Generation\EmitOpenApiTool;
use Altair\Mcp\Tool\Generation\EmitSdkTool;
use Altair\Mcp\Tool\Generation\RewindSpecTool;
use Altair\Mcp\Tool\Generation\ScaffoldTool;
use Altair\Mcp\Tool\Generation\WriteSpecTool;
use Altair\Mcp\Tool\Index\CallersTool;
use Altair\Mcp\Tool\Index\DeadCodeTool;
use Altair\Mcp\Tool\Index\FindUsagesTool;
use Altair\Mcp\Tool\Index\ImpactTool;
use Altair\Mcp\Tool\Index\ImplementersTool;
use Altair\Mcp\Tool\Introspection\ConfigDumpTool;
use Altair\Mcp\Tool\Introspection\ContainerInspectTool;
use Altair\Mcp\Tool\Introspection\ListenerShowTool;
use Altair\Mcp\Tool\Introspection\ListenersListTool;
use Altair\Mcp\Tool\Introspection\ManifestDiffTool;
use Altair\Mcp\Tool\Introspection\MiddlewareListTool;
use Altair\Mcp\Tool\Introspection\RouteShowTool;
use Altair\Mcp\Tool\Introspection\RoutesListTool;
use Altair\Mcp\Tool\Profile\ProfileCompareTool;
use Altair\Mcp\Tool\Profile\ProfileFlameTool;
use Altair\Mcp\Tool\Profile\ProfileListTool;
use Altair\Mcp\Tool\Profile\ProfileRunTool;
use Altair\Mcp\Tool\Profile\ProfileShowTool;
use Altair\Mcp\Tool\Verification\CheckDriftTool;
use Altair\Mcp\Tool\Verification\DoctorTool;
use Altair\Mcp\Tool\Verification\PhpstanTool;
use Altair\Mcp\Tool\Verification\RunTestsTool;

/**
 * The catalogue of v1 built-in tool classes the server registers.
 */
final class BuiltinTools
{
    /**
     * @return list<class-string>
     */
    public static function classes(): array
    {
        return [
            // Discovery / inspection
            ListPackagesTool::class,
            DescribePackageTool::class,
            ListSpecsTool::class,
            ReadSpecTool::class,
            ListEndpointsTool::class,
            DescribeEndpointTool::class,
            ContainerResolveTool::class,
            ListCommandsTool::class,
            // Generation / mutation
            WriteSpecTool::class,
            ScaffoldTool::class,
            RewindSpecTool::class,
            EmitOpenApiTool::class,
            EmitSdkTool::class,
            // Verification
            DoctorTool::class,
            RunTestsTool::class,
            CheckDriftTool::class,
            PhpstanTool::class,
            // Database
            DbQueryTool::class,
            DbSchemaTool::class,
            DbMigrateTool::class,
            PlanMigrationTool::class,
            // Introspection (read-only wrappers over the inspector commands)
            ContainerInspectTool::class,
            ConfigDumpTool::class,
            RoutesListTool::class,
            RouteShowTool::class,
            ListenersListTool::class,
            ListenerShowTool::class,
            MiddlewareListTool::class,
            ManifestDiffTool::class,
            // Symbol-usage index (read-only refactor intelligence)
            FindUsagesTool::class,
            ImplementersTool::class,
            CallersTool::class,
            DeadCodeTool::class,
            ImpactTool::class,
            // Sandboxed eval (the "let me check" primitive)
            EvalTool::class,
            // Sampling profiler (the "where is time spent?" loop)
            ProfileRunTool::class,
            ProfileListTool::class,
            ProfileShowTool::class,
            ProfileCompareTool::class,
            ProfileFlameTool::class,
        ];
    }
}
