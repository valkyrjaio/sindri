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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\RouteAttributeReader;
use Sindri\Ast\Contract\HttpRouteAttributeReaderContract;
use Sindri\Ast\Contract\HttpRouteMiddlewareReaderContract;
use Sindri\Ast\Contract\HttpRouteParameterReaderContract;
use Sindri\Ast\Data\HttpParameterData;
use Sindri\Ast\Data\HttpRouteData;
use Sindri\Ast\Result\HttpRouteAttributeResult;
use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Name;
use Valkyrja\Http\Routing\Attribute\Route\Path;
use Valkyrja\Http\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Http\Routing\Data\DynamicRoute as DynamicRouteModel;
use Valkyrja\Http\Routing\Data\Route as RouteModel;

use function is_string;

/**
 * Scans an HTTP controller class file for #[Route] / #[DynamicRoute] and related
 * sub-attributes and returns PHP-Parser Expr nodes ready for the data cache generator.
 *
 * Mirrors the logic of the framework's runtime AttributeRouteCollector but operates
 * entirely on AST without executing any PHP code.
 */
class HttpRouteAttributeReader extends RouteAttributeReader implements HttpRouteAttributeReaderContract
{
    public function __construct(
        private readonly HttpRouteParameterReaderContract $parameterReader = new HttpRouteParameterReader(),
        private readonly HttpRouteMiddlewareReaderContract $middlewareReader = new HttpRouteMiddlewareReader(),
    ) {
    }

    #[Override]
    public function readFile(string $filePath): HttpRouteAttributeResult
    {
        $context = $this->parseClassFile($filePath);

        if ($context === null) {
            return new HttpRouteAttributeResult();
        }

        [$class, $namespace, $useMap, $currentClass] = $context;

        $classPathPrefix = $this->extractClassPathPrefix($class, $useMap, $namespace, $currentClass);
        $classNamePrefix = $this->extractClassNamePrefix($class, $useMap, $namespace, $currentClass);

        return $this->buildRouteResult($class, $useMap, $namespace, $currentClass, $classPathPrefix, $classNamePrefix);
    }

    #[Override]
    protected function getRouteHandlerAttributeClass(): string
    {
        return RouteHandler::class;
    }

    /**
     * Extract a class-level #[Route\Path] prefix, if any.
     *
     * @param array<string, string> $useMap
     */
    protected function extractClassPathPrefix(
        Class_ $class,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string {
        foreach ($this->findAttributesOnNode($class, Path::class, $useMap, $namespace) as $attr) {
            $value = $this->extractExprValue($this->getAttrArg($attr->args, 'value', 0), $useMap, $namespace, $currentClass);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Extract a class-level #[Route\Name] prefix, if any.
     *
     * @param array<string, string> $useMap
     */
    protected function extractClassNamePrefix(
        Class_ $class,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string {
        foreach ($this->findAttributesOnNode($class, Name::class, $useMap, $namespace) as $attr) {
            $value = $this->extractExprValue($this->getAttrArg($attr->args, 'value', 0), $useMap, $namespace, $currentClass);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Scan all methods for #[Route] and #[DynamicRoute] attributes and build the result.
     *
     * @param array<string, string> $useMap
     */
    protected function buildRouteResult(
        Class_ $class,
        array $useMap,
        string $namespace,
        string $currentClass,
        string $classPathPrefix,
        string $classNamePrefix,
    ): HttpRouteAttributeResult {
        $routes    = [];
        $routeData = [];

        foreach ($class->getMethods() as $method) {
            foreach ($this->findAttributesOnNode($method, Route::class, $useMap, $namespace) as $attr) {
                $data = $this->buildRouteData($attr->args, $method, $useMap, $namespace, $currentClass, $classPathPrefix, $classNamePrefix, false);

                if ($data !== null) {
                    $routes[$data->name]    = $this->buildRouteExpr($data);
                    $routeData[$data->name] = $data;
                }
            }

            foreach ($this->findAttributesOnNode($method, DynamicRoute::class, $useMap, $namespace) as $attr) {
                $data = $this->buildRouteData($attr->args, $method, $useMap, $namespace, $currentClass, $classPathPrefix, $classNamePrefix, true);

                if ($data !== null) {
                    $routes[$data->name]    = $this->buildRouteExpr($data);
                    $routeData[$data->name] = $data;
                }
            }
        }

        return new HttpRouteAttributeResult(routes: $routes, routeData: $routeData);
    }

    /**
     * Collect all attribute arguments for a #[Route] / #[DynamicRoute] into an HttpRouteData.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildRouteData(
        array $args,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
        string $classPathPrefix,
        string $classNamePrefix,
        bool $isDynamic,
    ): HttpRouteData|null {
        $path = $this->extractStringArg($args, 'path', 0, $useMap, $namespace, $currentClass);
        $name = $this->extractStringArg($args, 'name', 1, $useMap, $namespace, $currentClass);

        if ($path === '' || $name === '') {
            return null;
        }

        $path = $this->updatePath($path, $classPathPrefix, $method, $useMap, $namespace, $currentClass);
        $name = $this->updateName($name, $classNamePrefix, $method, $useMap, $namespace, $currentClass);

        // Mirror RouteFactory::fromRoute(): any path containing '{' is dynamic
        $isDynamic = $isDynamic || str_contains($path, '{');

        $requestMethods = $this->middlewareReader->updateRequestMethods(
            $this->middlewareReader->extractInlineRequestMethods($args, $useMap, $namespace, $currentClass),
            $method,
            $useMap,
            $namespace,
            $currentClass,
        );

        [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $sendingResponseMiddleware, $terminatedMiddleware]
            = $this->middlewareReader->updateMiddleware(
                $method,
                $useMap,
                $namespace,
                $currentClass,
                $this->extractClassListArg($args, 'routeMatchedMiddleware', 5, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'routeDispatchedMiddleware', 6, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'throwableCaughtMiddleware', 7, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'sendingResponseMiddleware', 8, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'terminatedMiddleware', 9, $useMap, $namespace, $currentClass),
            );

        return new HttpRouteData(
            path: $path,
            name: $name,
            handler: $this->updateHandler($method, $useMap, $namespace, $currentClass),
            requestMethods: $requestMethods,
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            sendingResponseMiddleware: $sendingResponseMiddleware,
            terminatedMiddleware: $terminatedMiddleware,
            requestStruct: $this->middlewareReader->updateRequestStruct($method, $useMap, $namespace, $currentClass),
            responseStruct: $this->middlewareReader->updateResponseStruct($method, $useMap, $namespace, $currentClass),
            isDynamic: $isDynamic,
            parameters: $isDynamic ? $this->updateParameters($args, $method, $useMap, $namespace, $currentClass) : [],
        );
    }

    /**
     * Apply class-level and method-level #[Route\Path] to the path.
     *
     * @param array<string, string> $useMap
     */
    protected function updatePath(
        string $path,
        string $classPathPrefix,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string {
        if ($classPathPrefix !== '') {
            $path = rtrim($classPathPrefix, '/') . '/' . ltrim($path, '/');
        }

        foreach ($this->findAttributesOnNode($method, Path::class, $useMap, $namespace) as $attr) {
            $suffix = $this->extractExprValue($this->getAttrArg($attr->args, 'value', 0), $useMap, $namespace, $currentClass);

            if (is_string($suffix) && $suffix !== '') {
                $path = rtrim($path, '/') . '/' . ltrim($suffix, '/');
            }
        }

        return $path;
    }

    /**
     * Apply class-level and method-level #[Route\Name] to the route name.
     *
     * @param array<string, string> $useMap
     */
    protected function updateName(
        string $name,
        string $classNamePrefix,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string {
        if ($classNamePrefix !== '') {
            $name = $classNamePrefix . '.' . $name;
        }

        foreach ($this->findAttributesOnNode($method, Name::class, $useMap, $namespace) as $attr) {
            $suffix = $this->extractExprValue($this->getAttrArg($attr->args, 'value', 0), $useMap, $namespace, $currentClass);

            if (is_string($suffix) && $suffix !== '') {
                $name = $name . '.' . $suffix;
            }
        }

        return $name;
    }

    /**
     * Convert an HttpRouteData into a PHP-Parser New_ expression for the appropriate data class.
     *
     * Produces DynamicRoute for dynamic routes, Route for static routes.
     */
    protected function buildRouteExpr(HttpRouteData $data): Expr
    {
        $args = [
            $this->buildNamedArg('path', $this->buildStringExpr($data->path)),
            $this->buildNamedArg('name', $this->buildStringExpr($data->name)),
        ];

        if ($data->isDynamic && $data->parameters !== []) {
            $args[] = $this->buildNamedArg('parameters', $this->parameterReader->buildParameterListExpr($data->parameters));
        }

        if ($data->handler !== null) {
            $args[] = $this->buildNamedArg('handler', $this->buildHandlerExpr($data->handler));
        }

        if ($data->requestMethods !== []) {
            $args[] = $this->buildNamedArg('requestMethods', $this->buildEnumCaseArrayExpr($data->requestMethods));
        }

        array_push($args, ...$this->middlewareReader->buildRouteMiddlewareArgs($data));
        array_push($args, ...$this->middlewareReader->buildRouteStructArgs($data));

        $targetClass = $data->isDynamic ? DynamicRouteModel::class : RouteModel::class;

        return $this->buildNewExpr($targetClass, $args);
    }

    /**
     * Delegate to the parameter reader — preserves the protected method surface for subclasses and tests.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return HttpParameterData[]
     */
    protected function updateParameters(
        array $args,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        return $this->parameterReader->updateParameters($args, $method, $useMap, $namespace, $currentClass);
    }
}
