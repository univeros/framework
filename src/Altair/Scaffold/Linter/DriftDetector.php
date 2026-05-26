<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Linter;

use Altair\Scaffold\Emitter\Naming;
use Altair\Scaffold\Spec\Ast\Spec;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Compares a parsed Spec against what is actually on disk:
 *
 *  - input fields in spec ⇄ constructor properties of the Input DTO
 *  - validation rules in spec ⇄ entries returned by InputDto::rules()
 *  - output statuses in spec ⇄ Responder::statuses()
 *  - routes file ⇄ spec endpoints (action FQCN appearing in routes file)
 */
class DriftDetector
{
    private readonly Parser $phpParser;

    public function __construct(
        private readonly string $projectRoot,
        private readonly Naming $naming = new Naming(),
        ?Parser $phpParser = null,
        private readonly NodeFinder $nodeFinder = new NodeFinder(),
    ) {
        $this->phpParser = $phpParser ?? (new ParserFactory())->createForHostVersion();
    }

    public function detect(Spec $spec): DriftReport
    {
        $report = new DriftReport([]);
        $report = $this->checkInputFields($spec, $report);
        $report = $this->checkValidationRules($spec, $report);
        $report = $this->checkResponderStatuses($spec, $report);

        return $this->checkRoutes($spec, $report);
    }

    private function checkInputFields(Spec $spec, DriftReport $report): DriftReport
    {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . $this->naming->inputPath($spec);
        $class = $this->parseClass($path);
        if (!$class instanceof Class_) {
            return $report;
        }

        $found = [];
        $constructor = $this->findConstructor($class);
        if ($constructor instanceof ClassMethod) {
            foreach ($constructor->params as $param) {
                if ($param instanceof Param && $param->var instanceof Variable && \is_string($param->var->name)) {
                    $found[] = $param->var->name;
                }
            }
        }

        $specFields = array_map(static fn($field): string => $field->name, $spec->inputs);

        foreach ($specFields as $name) {
            if (!\in_array($name, $found, true)) {
                $report = $report->with(new DriftFinding(
                    DriftKind::MissingInputField,
                    \sprintf("Field '%s' is in the spec but missing from %s. Add `\$%s` to the constructor or remove from spec.", $name, $this->naming->inputShortName($spec), $name),
                    $path,
                ));
            }
        }

        foreach ($found as $name) {
            if (!\in_array($name, $specFields, true)) {
                $report = $report->with(new DriftFinding(
                    DriftKind::UnknownInputField,
                    \sprintf("Field '%s' is in %s but not in the spec. Add it to the spec or remove from the DTO.", $name, $this->naming->inputShortName($spec)),
                    $path,
                ));
            }
        }

        return $report;
    }

    private function checkValidationRules(Spec $spec, DriftReport $report): DriftReport
    {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . $this->naming->inputPath($spec);
        $class = $this->parseClass($path);
        if (!$class instanceof Class_) {
            return $report;
        }

        $rulesMethod = $this->findStaticMethod($class, 'rules');
        $rulesByField = $rulesMethod instanceof ClassMethod ? $this->extractRulesArray($rulesMethod) : [];

        foreach ($spec->inputs as $field) {
            $codeRules = $rulesByField[$field->name] ?? [];
            foreach ($field->rules as $rule) {
                if (!\in_array($rule, $codeRules, true)) {
                    $report = $report->with(new DriftFinding(
                        DriftKind::MissingValidationRule,
                        \sprintf("Rule '%s' for field '%s' is in spec but missing from %s::rules(). Add it or remove from spec.", $rule, $field->name, $this->naming->inputShortName($spec)),
                        $path,
                    ));
                }
            }
        }

        return $report;
    }

    private function checkResponderStatuses(Spec $spec, DriftReport $report): DriftReport
    {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . $this->naming->responderPath($spec);
        $class = $this->parseClass($path);
        if (!$class instanceof Class_) {
            return $report;
        }

        $statusesMethod = $this->findStaticMethod($class, 'statuses');
        $declared = $statusesMethod instanceof ClassMethod ? $this->extractIntList($statusesMethod) : [];

        foreach ($spec->outputs as $output) {
            if (!\in_array($output->status, $declared, true)) {
                $report = $report->with(new DriftFinding(
                    DriftKind::ResponderMissingStatus,
                    \sprintf('Status %d is in spec but not declared by %s::statuses(). Add it or remove from spec.', $output->status, $this->naming->responderShortName($spec)),
                    $path,
                ));
            }
        }

        return $report;
    }

    private function checkRoutes(Spec $spec, DriftReport $report): DriftReport
    {
        $path = $this->projectRoot . DIRECTORY_SEPARATOR . $this->naming->routesPath();
        if (!is_file($path)) {
            return $report->with(new DriftFinding(
                DriftKind::UnregisteredRoute,
                \sprintf("Routes file '%s' does not exist; spec endpoint %s %s has no route.", $this->naming->routesPath(), $spec->endpoint->method, $spec->endpoint->path),
                $path,
            ));
        }

        $contents = (string) file_get_contents($path);
        $actionFqcn = $this->naming->actionFqcn($spec);

        if (!str_contains($contents, $actionFqcn)) {
            return $report->with(new DriftFinding(
                DriftKind::UnregisteredRoute,
                \sprintf('Spec endpoint %s %s is not registered (no reference to %s in routes file).', $spec->endpoint->method, $spec->endpoint->path, $actionFqcn),
                $path,
            ));
        }

        return $report;
    }

    private function parseClass(string $path): ?Class_
    {
        if (!is_file($path)) {
            return null;
        }

        $code = (string) file_get_contents($path);
        $ast = $this->phpParser->parse($code);
        if ($ast === null) {
            return null;
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $resolved = $traverser->traverse($ast);

        $node = $this->nodeFinder->findFirstInstanceOf($resolved, Class_::class);
        \assert($node === null || $node instanceof Class_);

        return $node;
    }

    private function findConstructor(Class_ $class): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === '__construct') {
                return $method;
            }
        }

        return null;
    }

    private function findStaticMethod(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->getMethods() as $method) {
            if ($method->isStatic() && $method->name->toString() === $name) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractRulesArray(ClassMethod $method): array
    {
        $rules = [];

        $return = $this->nodeFinder->findFirstInstanceOf($method->stmts ?? [], Return_::class);
        if (!$return instanceof Return_ || !$return->expr instanceof Array_) {
            return $rules;
        }

        foreach ($return->expr->items as $item) {
            if (!$item->key instanceof String_) {
                continue;
            }

            if (!$item->value instanceof Array_) {
                continue;
            }

            $field = $item->key->value;
            $list = [];
            foreach ($item->value->items as $inner) {
                if ($inner->value instanceof String_) {
                    $list[] = $inner->value->value;
                }
            }

            $rules[$field] = $list;
        }

        return $rules;
    }

    /**
     * @return list<int>
     */
    private function extractIntList(ClassMethod $method): array
    {
        $return = $this->nodeFinder->findFirstInstanceOf($method->stmts ?? [], Return_::class);
        if (!$return instanceof Return_ || !$return->expr instanceof Array_) {
            return [];
        }

        $values = [];
        foreach ($return->expr->items as $item) {
            if ($item->value instanceof Int_) {
                $values[] = $item->value->value;
            }
        }

        return $values;
    }
}
