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
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\ParserFactory;
use RuntimeException;

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

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

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
            if (! ($item instanceof ArrayItem)) {
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
}