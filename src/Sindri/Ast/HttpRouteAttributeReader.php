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
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\VariadicPlaceholder;
use Sindri\Ast\Abstract\RouteAttributeReader;
use Sindri\Ast\Contract\HttpRouteAttributeReaderContract;
use Sindri\Ast\Data\HttpParameterData;
use Sindri\Ast\Data\HttpRouteData;
use Sindri\Ast\Result\HttpRouteAttributeResult;
use Valkyrja\Http\Message\Enum\RequestMethod as RequestMethodEnum;
use Valkyrja\Http\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\TerminatedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Attribute\Parameter;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;
use Valkyrja\Http\Routing\Attribute\Route\Name as RouteName;
use Valkyrja\Http\Routing\Attribute\Route\Path;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod;
use Valkyrja\Http\Routing\Attribute\Route\RequestStruct;
use Valkyrja\Http\Routing\Attribute\Route\ResponseStruct;
use Valkyrja\Http\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Http\Routing\Data\DynamicRoute as DynamicRouteModel;
use Valkyrja\Http\Routing\Data\Parameter as ParameterModel;
use Valkyrja\Http\Routing\Data\Route as RouteModel;

use function is_a;
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
        foreach ($this->findAttributesOnNode($class, RouteName::class, $useMap, $namespace) as $attr) {
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

        $requestMethods = $this->updateRequestMethods(
            $this->extractInlineRequestMethods($args, $useMap, $namespace, $currentClass),
            $method,
            $useMap,
            $namespace,
            $currentClass,
        );

        [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $sendingResponseMiddleware, $terminatedMiddleware]
            = $this->updateMiddleware(
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
            requestStruct: $this->updateRequestStruct($method, $useMap, $namespace, $currentClass),
            responseStruct: $this->updateResponseStruct($method, $useMap, $namespace, $currentClass),
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

        foreach ($this->findAttributesOnNode($method, RouteName::class, $useMap, $namespace) as $attr) {
            $suffix = $this->extractExprValue($this->getAttrArg($attr->args, 'value', 0), $useMap, $namespace, $currentClass);

            if (is_string($suffix) && $suffix !== '') {
                $name = $name . '.' . $suffix;
            }
        }

        return $name;
    }

    /**
     * Extract the inline requestMethods array from a #[Route] attribute (positional arg 3).
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return string[]
     */
    protected function extractInlineRequestMethods(array $args, array $useMap, string $namespace, string $currentClass): array
    {
        $requestMethodsExpr = $this->getAttrArg($args, 'requestMethods', 3);

        if (! $requestMethodsExpr instanceof Array_) {
            return [];
        }

        return $this->extractClassListFromArrayExpr($requestMethodsExpr, $useMap, $namespace, $currentClass);
    }

    /**
     * Collect request methods from #[Route\RequestMethod] and shorthand sub-attributes.
     *
     * @param string[]              $requestMethods Already collected methods (from inline arg)
     * @param array<string, string> $useMap
     *
     * @return string[]
     */
    protected function updateRequestMethods(
        array $requestMethods,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        foreach ($this->findAttributesOnNode($method, RequestMethod::class, $useMap, $namespace) as $attr) {
            foreach ($attr->args as $arg) {
                $value = $this->extractExprValue($arg->value, $useMap, $namespace, $currentClass);

                if (is_string($value) && $value !== '') {
                    $requestMethods[] = $value;
                }
            }
        }

        if ($requestMethods === []) {
            $requestMethods = [
                RequestMethodEnum::class . '::' . RequestMethodEnum::HEAD->name,
                RequestMethodEnum::class . '::' . RequestMethodEnum::GET->name,
            ];
        }

        return $requestMethods;
    }

    /**
     * Collect and classify #[Route\Middleware] attributes into the five middleware lists.
     *
     * @param array<string, string> $useMap
     * @param class-string[]        $routeMatchedMiddleware
     * @param class-string[]        $routeDispatchedMiddleware
     * @param class-string[]        $throwableCaughtMiddleware
     * @param class-string[]        $sendingResponseMiddleware
     * @param class-string[]        $terminatedMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[], class-string[]}
     */
    protected function updateMiddleware(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $sendingResponseMiddleware,
        array $terminatedMiddleware,
    ): array {
        foreach ($this->findAttributesOnNode($method, Middleware::class, $useMap, $namespace) as $attr) {
            $mwFqn = $this->extractExprValue($this->getAttrArg($attr->args, 'name', 0), $useMap, $namespace, $currentClass);

            if (! is_string($mwFqn) || $mwFqn === '') {
                continue;
            }

            [
                $routeMatchedMiddleware,
                $routeDispatchedMiddleware,
                $throwableCaughtMiddleware,
                $sendingResponseMiddleware,
                $terminatedMiddleware,
            ] = $this->classifyMiddleware($mwFqn, $routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $sendingResponseMiddleware, $terminatedMiddleware);
        }

        return [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $sendingResponseMiddleware, $terminatedMiddleware];
    }

    /**
     * Classify a single middleware FQN into the appropriate list(s) based on implemented contracts.
     *
     * @param class-string[] $routeMatchedMiddleware
     * @param class-string[] $routeDispatchedMiddleware
     * @param class-string[] $throwableCaughtMiddleware
     * @param class-string[] $sendingResponseMiddleware
     * @param class-string[] $terminatedMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[], class-string[]}
     */
    protected function classifyMiddleware(
        string $mwFqn,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $sendingResponseMiddleware,
        array $terminatedMiddleware,
    ): array {
        if (is_a($mwFqn, RouteMatchedMiddlewareContract::class, true)) {
            $routeMatchedMiddleware[] = $mwFqn;
        }

        if (is_a($mwFqn, RouteDispatchedMiddlewareContract::class, true)) {
            $routeDispatchedMiddleware[] = $mwFqn;
        }

        if (is_a($mwFqn, ThrowableCaughtMiddlewareContract::class, true)) {
            $throwableCaughtMiddleware[] = $mwFqn;
        }

        if (is_a($mwFqn, SendingResponseMiddlewareContract::class, true)) {
            $sendingResponseMiddleware[] = $mwFqn;
        }

        if (is_a($mwFqn, TerminatedMiddlewareContract::class, true)) {
            $terminatedMiddleware[] = $mwFqn;
        }

        return [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $sendingResponseMiddleware, $terminatedMiddleware];
    }

    /**
     * Resolve the request struct FQN from #[Route\RequestStruct], if any.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string|null
     */
    protected function updateRequestStruct(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string|null {
        foreach ($this->findAttributesOnNode($method, RequestStruct::class, $useMap, $namespace) as $attr) {
            $value = $this->extractExprValue($this->getAttrArg($attr->args, 'struct', 0), $useMap, $namespace, $currentClass);

            if (is_string($value) && $value !== '') {
                /** @var class-string */
                return $value;
            }
        }

        return null;
    }

    /**
     * Resolve the response struct FQN from #[Route\ResponseStruct], if any.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string|null
     */
    protected function updateResponseStruct(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string|null {
        foreach ($this->findAttributesOnNode($method, ResponseStruct::class, $useMap, $namespace) as $attr) {
            $value = $this->extractExprValue($this->getAttrArg($attr->args, 'struct', 0), $useMap, $namespace, $currentClass);

            if (is_string($value) && $value !== '') {
                /** @var class-string */
                return $value;
            }
        }

        return null;
    }

    /**
     * Collect parameters from inline DynamicRoute args and #[Parameter] attributes.
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
        return array_merge(
            $this->collectInlineParameters($args, $useMap, $namespace, $currentClass),
            $this->collectAttributeParameters($method, $useMap, $namespace, $currentClass),
            $this->collectMethodParamParameters($method, $useMap, $namespace, $currentClass),
        );
    }

    /**
     * Collect parameters from the inline parameters array (positional arg 2 on DynamicRoute).
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return HttpParameterData[]
     */
    protected function collectInlineParameters(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $inlineExpr = $this->getAttrArg($args, 'parameters', 2);

        if (! $inlineExpr instanceof Array_) {
            return [];
        }

        $parameters = [];

        foreach ($inlineExpr->items as $item) {
            if ($item === null) {
                continue;
            }

            $param = $this->buildParameterFromExpr($item->value, $useMap, $namespace, $currentClass);

            if ($param !== null) {
                $parameters[] = $param;
            }
        }

        return $parameters;
    }

    /**
     * Collect parameters from method-level #[Parameter] attributes.
     *
     * @param array<string, string> $useMap
     *
     * @return HttpParameterData[]
     */
    protected function collectAttributeParameters(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $parameters = [];

        foreach ($this->findAttributesOnNode($method, Parameter::class, $useMap, $namespace) as $attr) {
            $param = $this->buildParameterData($attr->args, $useMap, $namespace, $currentClass);

            if ($param !== null) {
                $parameters[] = $param;
            }
        }

        return $parameters;
    }

    /**
     * Collect parameters from #[Parameter] attributes placed on PHP method parameters.
     *
     * @param array<string, string> $useMap
     *
     * @return HttpParameterData[]
     */
    protected function collectMethodParamParameters(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $parameters = [];

        foreach ($method->params as $methodParam) {
            foreach ($this->findAttributesOnNode($methodParam, Parameter::class, $useMap, $namespace) as $attr) {
                $param = $this->buildParameterData($attr->args, $useMap, $namespace, $currentClass);

                if ($param !== null) {
                    $parameters[] = $param;
                }
            }
        }

        return $parameters;
    }

    /**
     * Attempt to build an HttpParameterData from an expression that may be a Parameter New_ node.
     *
     * @param array<string, string> $useMap
     */
    protected function buildParameterFromExpr(
        Expr $expr,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): HttpParameterData|null {
        if (! $expr instanceof New_) {
            return null;
        }

        return $this->buildParameterData($expr->args, $useMap, $namespace, $currentClass);
    }

    /**
     * Build an HttpParameterData from a #[Parameter] attribute argument list.
     *
     * @param array<array-key, Arg|VariadicPlaceholder> $args
     * @param array<string, string>                     $useMap
     */
    protected function buildParameterData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): HttpParameterData|null {
        /** @var Arg[] $args */
        $args  = array_values(array_filter($args, static fn ($a): bool => $a instanceof Arg));
        $name  = $this->extractStringArg($args, 'name', 0, $useMap, $namespace, $currentClass);
        $regex = $this->extractStringArg($args, 'regex', 1, $useMap, $namespace, $currentClass);

        if ($name === '' || $regex === '') {
            return null;
        }

        return new HttpParameterData(
            name: $name,
            regex: $regex,
            cast: $this->extractStringArg($args, 'cast', 2, $useMap, $namespace, $currentClass) ?: null,
            isOptional: $this->extractBoolArg($args, 'isOptional', 3, $useMap, $namespace, $currentClass),
            shouldCapture: $this->extractBoolArg($args, 'shouldCapture', 4, $useMap, $namespace, $currentClass, true),
        );
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
            $args[] = $this->buildNamedArg('parameters', $this->buildParameterListExpr($data->parameters));
        }

        if ($data->handler !== null) {
            $args[] = $this->buildNamedArg('handler', $this->buildHandlerExpr($data->handler));
        }

        if ($data->requestMethods !== []) {
            $args[] = $this->buildNamedArg('requestMethods', $this->buildEnumCaseArrayExpr($data->requestMethods));
        }

        array_push($args, ...$this->buildRouteMiddlewareArgs($data));
        array_push($args, ...$this->buildRouteStructArgs($data));

        $targetClass = $data->isDynamic ? DynamicRouteModel::class : RouteModel::class;

        return $this->buildNewExpr($targetClass, $args);
    }

    /**
     * Build the middleware named-arg list for an HttpRouteData.
     *
     * @return Arg[]
     */
    protected function buildRouteMiddlewareArgs(HttpRouteData $data): array
    {
        $args = [];

        if ($data->routeMatchedMiddleware !== []) {
            $args[] = $this->buildNamedArg('routeMatchedMiddleware', $this->buildClassArrayExpr($data->routeMatchedMiddleware));
        }

        if ($data->routeDispatchedMiddleware !== []) {
            $args[] = $this->buildNamedArg('routeDispatchedMiddleware', $this->buildClassArrayExpr($data->routeDispatchedMiddleware));
        }

        if ($data->throwableCaughtMiddleware !== []) {
            $args[] = $this->buildNamedArg('throwableCaughtMiddleware', $this->buildClassArrayExpr($data->throwableCaughtMiddleware));
        }

        if ($data->sendingResponseMiddleware !== []) {
            $args[] = $this->buildNamedArg('sendingResponseMiddleware', $this->buildClassArrayExpr($data->sendingResponseMiddleware));
        }

        if ($data->terminatedMiddleware !== []) {
            $args[] = $this->buildNamedArg('terminatedMiddleware', $this->buildClassArrayExpr($data->terminatedMiddleware));
        }

        return $args;
    }

    /**
     * Build the requestStruct/responseStruct named-arg list for an HttpRouteData.
     *
     * @return Arg[]
     */
    protected function buildRouteStructArgs(HttpRouteData $data): array
    {
        $args = [];

        if ($data->requestStruct !== null) {
            $args[] = $this->buildNamedArg('requestStruct', $this->buildClassConstExpr($data->requestStruct));
        }

        if ($data->responseStruct !== null) {
            $args[] = $this->buildNamedArg('responseStruct', $this->buildClassConstExpr($data->responseStruct));
        }

        return $args;
    }

    /**
     * Build an array expression of Parameter New_ nodes.
     *
     * @param HttpParameterData[] $parameters
     */
    protected function buildParameterListExpr(array $parameters): Array_
    {
        $items = [];

        foreach ($parameters as $parameter) {
            $items[] = new ArrayItem($this->buildParameterExpr($parameter));
        }

        return new Array_($items);
    }

    /**
     * Convert an HttpParameterData into a New_ expression for Parameter.
     */
    protected function buildParameterExpr(HttpParameterData $data): Expr
    {
        $regexExpr = str_contains($data->regex, '::')
            ? $this->buildEnumCaseExpr($data->regex)
            : $this->buildStringExpr($data->regex);

        $args = [
            $this->buildNamedArg('name', $this->buildStringExpr($data->name)),
            $this->buildNamedArg('regex', $regexExpr),
        ];

        if ($data->cast !== null) {
            $args[] = $this->buildNamedArg('cast', $this->buildEnumCaseExpr($data->cast));
        } else {
            $args[] = $this->buildNamedArg('cast', new ConstFetch(new Name('null')));
        }

        $args[] = $this->buildNamedArg('isOptional', $this->buildBoolExpr($data->isOptional));
        $args[] = $this->buildNamedArg('shouldCapture', $this->buildBoolExpr($data->shouldCapture));

        return $this->buildNewExpr(ParameterModel::class, $args);
    }
}