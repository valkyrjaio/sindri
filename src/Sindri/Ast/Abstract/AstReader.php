<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Ast\Abstract;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\ParserFactory;
use RuntimeException;
use Sindri\Ast\Data\HandlerData;

use function count;
use function is_float;
use function is_int;
use function is_string;
use function strlen;

/**
 * Shared AST parsing utilities for all provider reader implementations.
 *
 * Readers accept only file paths — no class-name resolution, no reflection, no
 * autoloader access. This makes the approach directly portable to non-PHP
 * implementations. Class-name → file-path resolution (e.g. via PSR-4 derivation)
 * is the caller's responsibility.
 *
 * All methods are protected so subclasses can override any step of the pipeline.
 */
abstract class AstReader
{
    /**
     * Read and parse a PHP source file, returning the raw statement list.
     *
     * @return Node[]
     */
    protected function parseFileToStmts(string $filePath): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            throw new RuntimeException("Cannot read file '$filePath'.");
        }

        $parser = new ParserFactory()->createForNewestSupportedVersion();

        return $parser->parse($code) ?? [];
    }

    /**
     * Separate a namespace wrapper from its inner statements.
     *
     * @param Node[] $stmts
     *
     * @return array{string, Node[]}
     */
    protected function unwrapNamespace(array $stmts): array
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Namespace_) {
                return [
                    $stmt->name?->toString() ?? '',
                    $stmt->stmts,
                ];
            }
        }

        return ['', $stmts];
    }

    /**
     * Build a map of alias/short-name → fully-qualified name from use statements.
     *
     * @param Node[] $stmts
     *
     * @return array<string, string>
     */
    protected function buildUseMap(array $stmts): array
    {
        $map = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Use_) {
                continue;
            }

            foreach ($stmt->uses as $use) {
                /** @var UseItem $use */
                $fqn = $use->name->toString();

                if ($use->alias instanceof Identifier) {
                    $alias = $use->alias->toString();
                } elseif (str_contains($fqn, '\\')) {
                    $alias = substr($fqn, (int) strrpos($fqn, '\\') + 1);
                } else {
                    $alias = $fqn;
                }

                $map[$alias] = $fqn;
            }
        }

        return $map;
    }

    /**
     * Find the first class node in a statement list.
     *
     * @param Node[] $stmts
     */
    protected function findClass(array $stmts): Class_|null
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Class_) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Index the class methods by name.
     *
     * @return array<string, ClassMethod>
     */
    protected function indexMethods(Class_ $class): array
    {
        $index = [];

        foreach ($class->getMethods() as $method) {
            $index[$method->name->toString()] = $method;
        }

        return $index;
    }

    /**
     * Extract the list of class-string values from a method's returned array.
     *
     * Expects a `return [A::class, B::class, ...]` statement. Items that are not
     * `ClassName::class` expressions are silently skipped.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string[]
     */
    protected function extractClassListFromValues(ClassMethod|null $method, array $useMap, string $namespace): array
    {
        if ($method === null) {
            return [];
        }

        $array = $this->findReturnedArray($method);

        if ($array === null) {
            return [];
        }

        $classes = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $resolved = $this->classConstFetchToFqn($item->value, $useMap, $namespace);

            if ($resolved !== null) {
                $classes[] = $resolved;
            }
        }

        return $classes;
    }

    /**
     * Extract the list of class-string keys from a method's returned array.
     *
     * Expects a `return [A::class => ..., B::class => ..., ...]` statement. Items
     * whose key is not a `ClassName::class` expression are silently skipped.
     *
     * Used for extracting service class names from `publishers()` in
     * ServiceProviderContract implementations (array keys are class names).
     *
     * @param array<string, string> $useMap
     *
     * @return class-string[]
     */
    protected function extractClassListFromKeys(ClassMethod|null $method, array $useMap, string $namespace): array
    {
        if ($method === null) {
            return [];
        }

        $array = $this->findReturnedArray($method);

        if ($array === null) {
            return [];
        }

        $classes = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $resolved = $this->classConstFetchToFqn($item->key, $useMap, $namespace);

            if ($resolved !== null) {
                $classes[] = $resolved;
            }
        }

        return $classes;
    }

    /**
     * Walk a method's statements to find the first returned Array_ expression.
     */
    protected function findReturnedArray(ClassMethod $method): Array_|null
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                return $stmt->expr;
            }
        }

        return null;
    }

    /**
     * Attempt to convert an expression node to a fully-qualified class name.
     *
     * Returns null when the expression is not a `ClassName::class` const-fetch.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string|null
     */
    protected function classConstFetchToFqn(Node|null $expr, array $useMap, string $namespace): string|null
    {
        if (! ($expr instanceof ClassConstFetch)
            || ! ($expr->class instanceof Name)
            || ! ($expr->name instanceof Identifier)
            || $expr->name->toString() !== 'class'
        ) {
            return null;
        }

        $shortName = $expr->class->toString();

        if ($expr->class instanceof FullyQualified) {
            /** @var class-string */
            return $shortName;
        }

        return $this->resolveClassName($shortName, $useMap, $namespace);
    }

    /**
     * Resolve a short or relative class name to a fully-qualified name.
     *
     * Resolution order:
     *   1. Exact alias match in use-map
     *   2. Prefix alias match (e.g. `Foo\Bar` where `Foo` is aliased to `Ns\Foo`)
     *   3. Prepend current namespace
     *
     * @param array<string, string> $useMap
     *
     * @return class-string
     */
    protected function resolveClassName(string $shortName, array $useMap, string $namespace): string
    {
        if (isset($useMap[$shortName])) {
            /** @var class-string */
            return $useMap[$shortName];
        }

        $firstSegment = strstr($shortName, '\\', before_needle: true);

        if ($firstSegment !== false && isset($useMap[$firstSegment])) {
            /** @var class-string */
            return $useMap[$firstSegment] . '\\' . substr($shortName, strlen($firstSegment) + 1);
        }

        /** @var class-string */
        return $namespace !== '' ? $namespace . '\\' . $shortName : $shortName;
    }

    /**
     * Resolve a Name node (from an attribute position) to a fully-qualified class name string.
     *
     * @param array<string, string> $useMap
     */
    protected function nameToFqn(Name $name, array $useMap, string $namespace): string
    {
        if ($name instanceof FullyQualified) {
            return $name->toString();
        }

        return $this->resolveClassName($name->toString(), $useMap, $namespace);
    }

    /**
     * Collect all Attribute nodes on the given node whose resolved FQN matches $attributeFqn.
     *
     * @param Node                  $node         Any node that carries attrGroups (Class_, ClassMethod, …)
     * @param string                $attributeFqn Fully-qualified attribute class name
     * @param array<string, string> $useMap
     *
     * @return Attribute[]
     */
    protected function findAttributesOnNode(Node $node, string $attributeFqn, array $useMap, string $namespace): array
    {
        /** @var AttributeGroup[] $attrGroups */
        $attrGroups = $node->attrGroups ?? [];
        $found      = [];

        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->nameToFqn($attr->name, $useMap, $namespace) === $attributeFqn) {
                    $found[] = $attr;
                }
            }
        }

        return $found;
    }

    /**
     * Extract a named or positional argument value from an attribute's arg list.
     *
     * @param Arg[] $args
     */
    protected function getAttrArg(array $args, string $name, int $position = 0): Node|null
    {
        foreach ($args as $arg) {
            if ($arg->name instanceof Identifier && $arg->name->toString() === $name) {
                return $arg->value;
            }
        }

        return $args[$position]->value ?? null;
    }

    /**
     * Convert a simple expression node to a PHP scalar value or HandlerData.
     *
     * Handles: string, int, float, bool (true/false/null), ClassConstFetch (FQN::CASE or FQN::class),
     * and two-element arrays of the form [ClassName::class, 'methodName'] (→ HandlerData).
     * Everything else returns null.
     *
     * @param array<string, string> $useMap
     */
    protected function extractExprValue(
        Node|null $expr,
        array $useMap,
        string $namespace,
        string $currentClass = '',
    ): string|int|float|bool|HandlerData|null {
        if ($expr === null) {
            return null;
        }

        if ($expr instanceof String_ || $expr instanceof InterpolatedString) {
            return $expr instanceof String_ ? $expr->value : null;
        }

        if ($expr instanceof Int_) {
            return $expr->value;
        }

        if ($expr instanceof Float_) {
            return $expr->value;
        }

        if ($expr instanceof UnaryMinus) {
            $inner = $this->extractExprValue($expr->expr, $useMap, $namespace, $currentClass);

            if (is_int($inner)) {
                return -$inner;
            }

            if (is_float($inner)) {
                return -$inner;
            }

            return null;
        }

        if ($expr instanceof ConstFetch) {
            $lower = strtolower($expr->name->toString());

            if ($lower === 'true') {
                return true;
            }

            if ($lower === 'false') {
                return false;
            }

            return null;
        }

        if ($expr instanceof ClassConstFetch
            && $expr->class instanceof Name
            && $expr->name instanceof Identifier
        ) {
            $caseName = $expr->name->toString();

            if ($caseName === 'class') {
                $className = $expr->class->toString();

                if ($className === 'self' || $className === 'static') {
                    return $currentClass;
                }

                /** @var class-string */
                return $this->nameToFqn($expr->class, $useMap, $namespace);
            }

            // Enum case: resolve to "FQN::CASE"
            $className = $expr->class->toString();

            if ($className === 'self' || $className === 'static') {
                return $currentClass . '::' . $caseName;
            }

            return $this->nameToFqn($expr->class, $useMap, $namespace) . '::' . $caseName;
        }

        if ($expr instanceof Array_) {
            return $this->extractHandlerFromArray($expr, $useMap, $namespace, $currentClass);
        }

        return null;
    }

    /**
     * Attempt to extract a HandlerData from a two-element array `[ClassName::class, 'methodName']`.
     *
     * Returns null when the array does not match this exact pattern.
     *
     * @param array<string, string> $useMap
     */
    protected function extractHandlerFromArray(
        Array_ $array,
        array $useMap,
        string $namespace,
        string $currentClass = '',
    ): HandlerData|null {
        if (count($array->items) !== 2) {
            return null;
        }

        $classItem  = $array->items[0];
        $methodItem = $array->items[1];

        if ($classItem === null || $methodItem === null) {
            return null;
        }

        $classValue = $this->extractExprValue($classItem->value, $useMap, $namespace, $currentClass);

        if (! is_string($classValue) || $classValue === '') {
            return null;
        }

        $methodValue = $this->extractExprValue($methodItem->value, $useMap, $namespace, $currentClass);

        if (! is_string($methodValue) || $methodValue === '') {
            return null;
        }

        return new HandlerData(class: $classValue, method: $methodValue);
    }

    /**
     * Extract a list of FQN strings from an array expression whose items are ClassName::class fetches.
     *
     * Used to read middleware arrays inside attribute arguments.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string[]
     */
    protected function extractClassListFromArrayExpr(
        Array_ $array,
        array $useMap,
        string $namespace,
        string $currentClass = '',
    ): array {
        $classes = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $value = $this->extractExprValue($item->value, $useMap, $namespace, $currentClass);

            if (is_string($value) && $value !== '') {
                /** @var class-string */
                $classes[] = $value;
            }
        }

        return $classes;
    }

    // -------------------------------------------------------------------------
    // AST-building helpers — convert PHP values back to PHP-Parser Expr nodes
    // -------------------------------------------------------------------------

    /**
     * Build a String_ node.
     */
    protected function buildStringExpr(string $value): String_
    {
        return new String_($value);
    }

    /**
     * Build a `ClassName::class` ClassConstFetch node from a fully-qualified class name.
     */
    protected function buildClassConstExpr(string $fqn): ClassConstFetch
    {
        return new ClassConstFetch(new FullyQualified($fqn), new Identifier('class'));
    }

    /**
     * Build a `ClassName::CASE` ClassConstFetch node from a "FQN::CASE" string.
     *
     * Returns a String_ fallback if the string does not contain "::".
     */
    protected function buildEnumCaseExpr(string $fqnColonCase): Expr
    {
        $pos = strpos($fqnColonCase, '::');

        if ($pos === false) {
            return new String_($fqnColonCase);
        }

        $fqn  = substr($fqnColonCase, 0, $pos);
        $case = substr($fqnColonCase, $pos + 2);

        return new ClassConstFetch(new FullyQualified($fqn), new Identifier($case));
    }

    /**
     * Build an `[ClassName::class, 'methodName']` Array_ node from a HandlerData.
     */
    protected function buildHandlerExpr(HandlerData $handler): Array_
    {
        return new Array_([
            new ArrayItem($this->buildClassConstExpr($handler->class)),
            new ArrayItem(new String_($handler->method)),
        ]);
    }

    /**
     * Build an array expression of `ClassName::class` items from a list of FQN strings.
     *
     * @param class-string[] $classes
     */
    protected function buildClassArrayExpr(array $classes): Array_
    {
        $items = [];

        foreach ($classes as $fqn) {
            $items[] = new ArrayItem($this->buildClassConstExpr($fqn));
        }

        return new Array_($items);
    }

    /**
     * Build a named Arg node.
     */
    protected function buildNamedArg(string $name, Expr $value): Arg
    {
        return new Arg(value: $value, name: new Identifier($name));
    }

    /**
     * Build a `new ClassName(args...)` New_ expression.
     *
     * @param class-string $fqn
     * @param Arg[]        $args
     */
    protected function buildNewExpr(string $fqn, array $args): New_
    {
        return new New_(new FullyQualified($fqn), $args);
    }

    /**
     * Build a true/false ConstFetch node.
     */
    protected function buildBoolExpr(bool $value): ConstFetch
    {
        return new ConstFetch(new Name($value ? 'true' : 'false'));
    }

    /**
     * Build an Array_ expression of String_ nodes from a plain string list.
     *
     * @param string[] $values
     */
    protected function buildStringArrayExpr(array $values): Array_
    {
        $items = [];

        foreach ($values as $value) {
            $items[] = new ArrayItem($this->buildStringExpr($value));
        }

        return new Array_($items);
    }

    /**
     * Build an Array_ of ClassConstFetch or enum-case nodes from a list of "FQN::CASE" strings.
     *
     * @param string[] $enumCases
     */
    protected function buildEnumCaseArrayExpr(array $enumCases): Array_
    {
        $items = [];

        foreach ($enumCases as $case) {
            $items[] = new ArrayItem($this->buildEnumCaseExpr($case));
        }

        return new Array_($items);
    }
}
