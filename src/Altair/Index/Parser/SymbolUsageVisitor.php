<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Parser;

use Altair\Index\Model\Symbol;
use Altair\Index\Model\SymbolKind;
use Altair\Index\Model\Usage;
use Altair\Index\Model\UsageKind;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects symbol declarations and usages from a single, name-resolved AST.
 *
 * Expects to run after php-parser's {@see \PhpParser\NodeVisitor\NameResolver}
 * (so every {@see Node\Name} is already fully qualified) and alongside
 * {@see \PhpParser\NodeVisitor\ParentConnectingVisitor} (so a property fetch can
 * tell whether it is the target of an assignment). Resolution is AST-only: it
 * never infers the runtime type of an expression, so `$obj->method()` on an
 * untyped variable is not linked, but `$this->`, `self::`, `parent::`, and
 * `Class::` references are.
 */
final class SymbolUsageVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<Symbol>
     */
    private array $symbols = [];

    /**
     * @var list<Usage>
     */
    private array $usages = [];

    /**
     * @var list<?string>
     */
    private array $classStack = [];

    /**
     * @var list<?string>
     */
    private array $parentStack = [];

    /**
     * @var list<string>
     */
    private array $contextStack = [];

    public function __construct(private readonly string $file) {}

    public function enterNode(Node $node): null
    {
        match (true) {
            $node instanceof Class_ => $this->enterClass($node),
            $node instanceof Interface_ => $this->enterClassLike($node, SymbolKind::Interface_, $node->extends),
            $node instanceof Trait_ => $this->enterClassLike($node, SymbolKind::Trait_, []),
            $node instanceof Enum_ => $this->enterClassLike($node, SymbolKind::Enum_, $node->implements, UsageKind::Implements_),
            $node instanceof ClassMethod => $this->enterMethod($node),
            $node instanceof Function_ => $this->contextStack[] = $node->name->toString() . '()',
            $node instanceof Property => $this->enterProperty($node),
            $node instanceof ClassConst => $this->enterClassConst($node),
            $node instanceof Param => $this->enterParam($node),
            $node instanceof New_ => $this->recordClassRef($node->class, $node->getStartLine(), UsageKind::New_),
            $node instanceof StaticCall => $this->enterStaticCall($node),
            $node instanceof MethodCall, $node instanceof NullsafeMethodCall => $this->enterInstanceCall($node),
            $node instanceof StaticPropertyFetch => $this->enterStaticPropertyFetch($node),
            $node instanceof PropertyFetch, $node instanceof NullsafePropertyFetch => $this->enterInstancePropertyFetch($node),
            $node instanceof ClassConstFetch => $this->enterClassConstFetch($node),
            $node instanceof Attribute => $this->recordClassRef($node->name, $node->getStartLine(), UsageKind::Attribute),
            default => null,
        };

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Class_ || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) {
            array_pop($this->classStack);
            array_pop($this->parentStack);
        }

        if ($node instanceof ClassMethod || $node instanceof Function_) {
            array_pop($this->contextStack);
        }

        return null;
    }

    /**
     * @return list<Symbol>
     */
    public function symbols(): array
    {
        return $this->symbols;
    }

    /**
     * @return list<Usage>
     */
    public function usages(): array
    {
        return $this->usages;
    }

    private function enterClass(Class_ $node): void
    {
        $parent = $node->extends instanceof Name ? $node->extends->toString() : null;
        $this->enterClassLike($node, SymbolKind::Class_, $node->implements, UsageKind::Implements_, $parent, $node->isReadonly());

        if ($node->extends instanceof Name) {
            $this->recordClassRef($node->extends, $node->extends->getStartLine(), UsageKind::Extends_);
        }
    }

    /**
     * @param array<Node\Name> $interfaces
     */
    private function enterClassLike(
        ClassLike $node,
        SymbolKind $kind,
        array $interfaces,
        UsageKind $interfaceKind = UsageKind::Implements_,
        ?string $parent = null,
        bool $isReadonly = false,
    ): void {
        $fqn = $node->namespacedName?->toString();
        $this->classStack[] = $fqn;
        $this->parentStack[] = $parent;

        if ($fqn === null) {
            return; // anonymous class: members still resolve against a null owner
        }

        $this->symbols[] = new Symbol($fqn, $kind, $this->file, $node->getStartLine(), isReadonly: $isReadonly);

        foreach ($interfaces as $interface) {
            $this->recordClassRef($interface, $interface->getStartLine(), $interfaceKind);
        }
    }

    private function enterMethod(ClassMethod $node): void
    {
        $owner = $this->currentClass();
        $name = $node->name->toString();
        $this->contextStack[] = $owner === null ? $name . '()' : $owner . '::' . $name;

        if ($owner !== null) {
            $this->symbols[] = new Symbol(
                $owner . '::' . $name,
                SymbolKind::Method,
                $this->file,
                $node->getStartLine(),
                $this->visibility($node->isProtected(), $node->isPrivate()),
                isStatic: $node->isStatic(),
            );
        }

        $this->recordType($node->returnType, $node->getStartLine());
    }

    private function enterProperty(Property $node): void
    {
        $owner = $this->currentClass();
        $visibility = $this->visibility($node->isProtected(), $node->isPrivate());

        foreach ($node->props as $prop) {
            if ($owner !== null) {
                $this->symbols[] = new Symbol(
                    $owner . '::$' . $prop->name->toString(),
                    SymbolKind::Property,
                    $this->file,
                    $prop->getStartLine(),
                    $visibility,
                    $node->isReadonly(),
                    $node->isStatic(),
                );
            }
        }

        $this->recordType($node->type, $node->getStartLine());
    }

    private function enterClassConst(ClassConst $node): void
    {
        $owner = $this->currentClass();
        if ($owner === null) {
            return;
        }

        $visibility = $this->visibility($node->isProtected(), $node->isPrivate());
        foreach ($node->consts as $const) {
            $this->symbols[] = new Symbol(
                $owner . '::' . $const->name->toString(),
                SymbolKind::Constant,
                $this->file,
                $const->getStartLine(),
                $visibility,
            );
        }
    }

    private function enterParam(Param $node): void
    {
        $this->recordType($node->type, $node->getStartLine());

        $owner = $this->currentClass();
        if (!$node->isPromoted() || $owner === null || !$node->var instanceof Variable || !\is_string($node->var->name)) {
            return;
        }

        $this->symbols[] = new Symbol(
            $owner . '::$' . $node->var->name,
            SymbolKind::Property,
            $this->file,
            $node->getStartLine(),
            $this->paramVisibility($node->flags),
            $node->isReadonly(),
        );
    }

    private function enterStaticCall(StaticCall $node): void
    {
        $target = $this->resolveClassName($node->class);
        if ($target !== null && $node->name instanceof Identifier) {
            $this->usages[] = $this->usage($target . '::' . $node->name->toString(), $node->getStartLine(), UsageKind::Call);
        }
    }

    private function enterInstanceCall(MethodCall|NullsafeMethodCall $node): void
    {
        $owner = $this->thisOwner($node->var);
        if ($owner !== null && $node->name instanceof Identifier) {
            $this->usages[] = $this->usage($owner . '::' . $node->name->toString(), $node->getStartLine(), UsageKind::Call);
        }
    }

    private function enterStaticPropertyFetch(StaticPropertyFetch $node): void
    {
        $target = $this->resolveClassName($node->class);
        if ($target !== null && $node->name instanceof VarLikeIdentifier) {
            $this->usages[] = $this->usage($target . '::$' . $node->name->toString(), $node->getStartLine(), $this->accessKind($node));
        }
    }

    private function enterInstancePropertyFetch(PropertyFetch|NullsafePropertyFetch $node): void
    {
        $owner = $this->thisOwner($node->var);
        if ($owner !== null && $node->name instanceof Identifier) {
            $this->usages[] = $this->usage($owner . '::$' . $node->name->toString(), $node->getStartLine(), $this->accessKind($node));
        }
    }

    private function enterClassConstFetch(ClassConstFetch $node): void
    {
        $target = $this->resolveClassName($node->class);
        if ($target === null || !$node->name instanceof Identifier) {
            return;
        }

        $name = $node->name->toString();
        $fqn = $name === 'class' ? $target : $target . '::' . $name;
        $this->usages[] = $this->usage($fqn, $node->getStartLine(), UsageKind::ClassConstant);
    }

    private function recordClassRef(Node $class, int $line, UsageKind $kind): void
    {
        if ($class instanceof Name && !$class->isSpecialClassName()) {
            $this->usages[] = $this->usage($class->toString(), $line, $kind);
        }
    }

    private function recordType(?Node $type, int $line): void
    {
        if ($type instanceof Name) {
            $this->recordClassRef($type, $line, UsageKind::TypeHint);

            return;
        }

        if ($type instanceof NullableType) {
            $this->recordType($type->type, $line);

            return;
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $member) {
                $this->recordType($member, $line);
            }
        }
    }

    private function accessKind(StaticPropertyFetch|PropertyFetch|NullsafePropertyFetch $node): UsageKind
    {
        $parent = $node->getAttribute('parent');

        $isWriteTarget = ($parent instanceof Assign || $parent instanceof AssignRef || $parent instanceof AssignOp)
            && $parent->var === $node;
        $isIncDec = ($parent instanceof PreInc || $parent instanceof PreDec || $parent instanceof PostInc || $parent instanceof PostDec)
            && $parent->var === $node;

        return $isWriteTarget || $isIncDec ? UsageKind::PropertyWrite : UsageKind::PropertyRead;
    }

    private function thisOwner(Expr $var): ?string
    {
        return $var instanceof Variable && $var->name === 'this' ? $this->currentClass() : null;
    }

    private function resolveClassName(Node $class): ?string
    {
        if (!$class instanceof Name) {
            return null;
        }

        return match (strtolower($class->toString())) {
            'self', 'static' => $this->currentClass(),
            'parent' => $this->currentParent(),
            default => $class->toString(),
        };
    }

    private function usage(string $fqn, int $line, UsageKind $kind): Usage
    {
        // Method/function usages carry the enclosing callable; class-level
        // usages (extends/implements/attribute) fall back to the declaring
        // class so implementers/extenders queries can name the subject.
        return new Usage($fqn, $this->file, $line, $kind, $this->currentContext() ?? $this->currentClass());
    }

    private function visibility(bool $protected, bool $private): string
    {
        return match (true) {
            $private => 'private',
            $protected => 'protected',
            default => 'public',
        };
    }

    private function paramVisibility(int $flags): string
    {
        return match (true) {
            ($flags & Modifiers::PRIVATE) !== 0 => 'private',
            ($flags & Modifiers::PROTECTED) !== 0 => 'protected',
            default => 'public',
        };
    }

    private function currentClass(): ?string
    {
        return $this->classStack === [] ? null : $this->classStack[\count($this->classStack) - 1];
    }

    private function currentParent(): ?string
    {
        return $this->parentStack === [] ? null : $this->parentStack[\count($this->parentStack) - 1];
    }

    private function currentContext(): ?string
    {
        return $this->contextStack === [] ? null : $this->contextStack[\count($this->contextStack) - 1];
    }
}
