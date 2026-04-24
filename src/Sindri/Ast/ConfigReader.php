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

namespace Sindri\Ast;

use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\ConfigReaderContract;
use Sindri\Ast\Result\ConfigResult;

use function count;
use function dirname;

/**
 * Reads an application config class file via AST and extracts the values
 * needed by GenerateDataFromAst without executing the PHP file.
 *
 * Expects a class with a constructor that calls parent::__construct() with
 * named arguments (namespace, dir, dataPath, dataNamespace, providers).
 * __DIR__ in the dir argument is resolved relative to the config file path.
 */
class ConfigReader extends AstReader implements ConfigReaderContract
{
    #[Override]
    public function readFile(string $filePath): ConfigResult
    {
        $context = $this->parseClassFile($filePath);

        if ($context === null) {
            return new ConfigResult();
        }

        [$classNode, $namespace, $useMap] = $context;

        $args = $this->findConstructorArgs($classNode);

        if ($args === null) {
            return new ConfigResult();
        }

        return $this->buildConfigResult($args, $filePath, $namespace, $useMap);
    }

    /**
     * Locate the constructor and extract its parent::__construct() arguments.
     *
     * @return Arg[]|null
     */
    protected function findConstructorArgs(Class_ $class): array|null
    {
        $construct = $this->indexMethods($class)['__construct'] ?? null;

        return $construct !== null ? $this->findParentConstructArgs($construct) : null;
    }

    /**
     * Walk the constructor body to find parent::__construct() and return its args.
     *
     * @return Arg[]|null
     */
    protected function findParentConstructArgs(ClassMethod $method): array|null
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if (! $stmt instanceof Expression || ! $this->isParentConstruct($stmt->expr)) {
                continue;
            }

            /** @var StaticCall $call */
            $call = $stmt->expr;

            /** @var Arg[] */
            return $call->args;
        }

        return null;
    }

    /**
     * Return true when the expression is a parent::__construct() call.
     */
    protected function isParentConstruct(Node $expr): bool
    {
        return $expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->class->toString() === 'parent'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === '__construct';
    }

    /**
     * Build a ConfigResult from the extracted constructor argument list.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildConfigResult(array $args, string $filePath, string $namespace, array $useMap): ConfigResult
    {
        $fileDir       = dirname($filePath);
        $baseNamespace = $this->extractStringNamedArg($args, 'namespace');
        $srcDir        = $this->computeSrcDir($fileDir, $namespace, $baseNamespace);
        $dataPath      = $this->resolveDataPath($args, $fileDir);
        $dataNamespace = $this->extractStringNamedArg($args, 'dataNamespace');

        if ($baseNamespace === '' || $srcDir === '' || $dataPath === '' || $dataNamespace === '') {
            return new ConfigResult();
        }

        return new ConfigResult(
            namespace: $baseNamespace,
            dir: $srcDir,
            dataPath: $dataPath,
            dataNamespace: $dataNamespace,
            providers: $this->extractClassListNamedArg($args, 'providers', $useMap, $namespace),
        );
    }

    /**
     * Resolve the data path, prepending the app root when the value is relative.
     *
     * @param Arg[] $args
     */
    protected function resolveDataPath(array $args, string $fileDir): string
    {
        $appRoot  = $this->resolvePathExpr($this->findNamedArgValue($args, 'dir'), $fileDir);
        $dataPath = $this->extractStringNamedArg($args, 'dataPath');

        if ($dataPath !== '' && ! str_starts_with($dataPath, '/')) {
            $dataPath = rtrim($appRoot, '/') . '/' . $dataPath;
        }

        return $dataPath;
    }

    /**
     * Find the value node of a named argument from an arg list.
     *
     * @param Arg[] $args
     */
    protected function findNamedArgValue(array $args, string $name): Node|null
    {
        foreach ($args as $arg) {
            if ($arg->name instanceof Identifier
                && $arg->name->toString() === $name
            ) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * Extract a plain string value from a named argument.
     *
     * @param Arg[] $args
     */
    protected function extractStringNamedArg(array $args, string $name): string
    {
        $node = $this->findNamedArgValue($args, $name);

        if ($node instanceof String_) {
            return $node->value;
        }

        return '';
    }

    /**
     * Resolve a path expression to an absolute string.
     *
     * Handles: String_ literals, __DIR__ magic constant, and Concat of both.
     * __DIR__ is resolved to dirname($fileDir) at read time.
     */
    protected function resolvePathExpr(Node|null $expr, string $fileDir): string
    {
        if ($expr === null) {
            return '';
        }

        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof Dir) {
            return $fileDir;
        }

        if ($expr instanceof Concat) {
            $left  = $this->resolvePathExpr($expr->left, $fileDir);
            $right = $this->resolvePathExpr($expr->right, $fileDir);
            $path  = $left . $right;

            $realpath = realpath($path);

            return $realpath !== false ? $realpath : $path;
        }

        return '';
    }

    /**
     * Derive the PSR-4 source root for the base namespace from the config file's location.
     *
     * The config file lives at depth (fileNamespace - baseNamespace) below the namespace root.
     * Ascending that many levels from the file's directory yields the PSR-4 root where
     * base\Sub\Class maps to root/Sub/Class.php.
     */
    protected function computeSrcDir(string $fileDir, string $fileNamespace, string $baseNamespace): string
    {
        $fileDepth = $fileNamespace !== '' ? count(explode('\\', $fileNamespace)) : 0;
        $baseDepth = $baseNamespace !== '' ? count(explode('\\', $baseNamespace)) : 0;
        $levels    = max(0, $fileDepth - $baseDepth);

        $srcDir = $fileDir;

        for ($i = 0; $i < $levels; $i++) {
            $srcDir = dirname($srcDir);
        }

        return $srcDir;
    }

    /**
     * Extract a class-string list from a named array argument.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return class-string[]
     */
    protected function extractClassListNamedArg(array $args, string $name, array $useMap, string $namespace): array
    {
        $node = $this->findNamedArgValue($args, $name);

        if (! $node instanceof Array_) {
            return [];
        }

        return $this->extractClassListFromArrayExpr($node, $useMap, $namespace);
    }
}