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
use PhpParser\Node\Expr;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\RouteProviderReaderContract;
use Sindri\Ast\Result\RouteProviderResult;

/**
 * Reads a single CliRouteProviderContract or HttpRouteProviderContract source file
 * and extracts route data. Both provider types share this reader because their
 * contracts are structurally identical.
 *
 * Two sources of routes are read:
 *
 *   getControllerClasses() — returns class names of attributed controller classes;
 *                             a subsequent attribute-reader pass will scan each class
 *                             for #[Route] attributes and build full route data objects.
 *
 *   getRoutes()            — returns manually-defined route data objects;
 *                             their exact shapes are preserved as-is.
 *                             (AST parsing of new-expression arguments to reconstruct
 *                             these objects is a planned next step.)
 *
 * All methods and properties are protected to allow easy subclass customization.
 */
class RouteProviderReader extends AstReader implements RouteProviderReaderContract
{
    protected const string METHOD_CONTROLLER_CLASSES = 'getControllerClasses';
    protected const string METHOD_ROUTES             = 'getRoutes';

    /**
     * @inheritDoc
     */
    #[Override]
    public function readFile(string $filePath): RouteProviderResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $stmts] = $this->unwrapNamespace($stmts);

        $useMap    = $this->buildUseMap($stmts);
        $classNode = $this->findClass($stmts);

        if ($classNode === null) {
            return new RouteProviderResult();
        }

        $methods = $this->indexMethods($classNode);

        return new RouteProviderResult(
            controllerClasses: $this->extractClassListFromValues($methods[self::METHOD_CONTROLLER_CLASSES] ?? null, $useMap, $namespace),
            routes: $this->extractRoutes($methods[self::METHOD_ROUTES] ?? null, $useMap, $namespace),
        );
    }

    /**
     * Extract manually-defined route objects from the getRoutes() method.
     *
     * Currently returns an empty array. Parsing new-expression arguments to
     * reconstruct route data objects from their AST representation is a planned
     * next step.
     *
     * @param array<string, string> $useMap
     *
     * @return Expr[]
     */
    protected function extractRoutes(mixed $method, array $useMap, string $namespace): array
    {
        return [];
    }
}
