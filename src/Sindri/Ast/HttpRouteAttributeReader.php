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

use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\HttpRouteAttributeReaderContract;
use Sindri\Ast\Data\HandlerData;
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
use function is_bool;
use function is_string;

/**
 * Scans an HTTP controller class file for #[Route] / #[DynamicRoute] and related
 * sub-attributes and returns PHP-Parser Expr nodes ready for the data cache generator.
 *
 * Mirrors the logic of the framework's runtime AttributeRouteCollector but operates
 * entirely on AST without executing any PHP code.
 */
class HttpRouteAttributeReader extends AstReader implements HttpRouteAttributeReaderContract
{
    public function readFile(string $filePath): HttpRouteAttributeResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $innerStmts] = $this->unwrapNamespace($stmts);

        $useMap = $this->buildUseMap($innerStmts);
        $class  = $this->findClass($innerStmts);

        if ($class === null) {
            return new HttpRouteAttributeResult();
        }

        $currentClass = $namespace !== ''
            ? $namespace . '\\' . ($class->name?->toString() ?? '')
            : ($class->name?->toString() ?? '');

        // Collect class-level path/name prefixes
        $classPathPrefix = $this->extractClassPathPrefix($class, $useMap, $namespace, $currentClass);
        $classNamePrefix = $this->extractClassNamePrefix($class, $useMap, $namespace, $currentClass);

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
        $path = $this->extractExprValue($this->getAttrArg($args, 'path', 0), $useMap, $namespace, $currentClass);
        $name = $this->extractExprValue($this->getAttrArg($args, 'name', 1), $useMap, $namespace, $currentClass);

        if (! is_string($path) || $path === '' || ! is_string($name) || $name === '') {
            return null;
        }

        $path = $this->updatePath($path, $classPathPrefix, $method, $useMap, $namespace, $currentClass);
        $name = $this->updateName($name, $classNamePrefix, $method, $useMap, $namespace, $currentClass);

        // Mirror RouteFactory::fromRoute(): any path containing '{' is dynamic
        $isDynamic = $isDynamic || str_contains($path, '{');

        $requestMethods = $this->extractInlineRequestMethods($args, $useMap, $namespace, $currentClass);
        $requestMethods = $this->updateRequestMethods($requestMethods, $method, $useMap, $namespace, $currentClass);

        $routeMatchedMiddleware    = [];
        $routeDispatchedMiddleware = [];
        $throwableCaughtMiddleware = [];
        $sendingResponseMiddleware = [];
        $terminatedMiddleware      = [];

        $matchedExpr    = $this->getAttrArg($args, 'routeMatchedMiddleware', 5);
        $dispatchedExpr = $this->getAttrArg($args, 'routeDispatchedMiddleware', 6);
        $throwableExpr  = $this->getAttrArg($args, 'throwableCaughtMiddleware', 7);
        $sendingExpr    = $this->getAttrArg($args, 'sendingResponseMiddleware', 8);
        $terminatedExpr = $this->getAttrArg($args, 'terminatedMiddleware', 9);

        if ($matchedExpr instanceof Array_) {
            $routeMatchedMiddleware = $this->extractClassListFromArrayExpr($matchedExpr, $useMap, $namespace, $currentClass);
        }

        if ($dispatchedExpr instanceof Array_) {
            $routeDispatchedMiddleware = $this->extractClassListFromArrayExpr($dispatchedExpr, $useMap, $namespace, $currentClass);
        }

        if ($throwableExpr instanceof Array_) {
            $throwableCaughtMiddleware = $this->extractClassListFromArrayExpr($throwableExpr, $useMap, $namespace, $currentClass);
        }

        if ($sendingExpr instanceof Array_) {
            $sendingResponseMiddleware = $this->extractClassListFromArrayExpr($sendingExpr, $useMap, $namespace, $currentClass);
        }

        if ($terminatedExpr instanceof Array_) {
            $terminatedMiddleware = $this->extractClassListFromArrayExpr($terminatedExpr, $useMap, $namespace, $currentClass);
        }

        [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $sendingResponseMiddleware,
            $terminatedMiddleware,
        ] = $this->updateMiddleware(
            $method,
            $useMap,
            $namespace,
            $currentClass,
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $sendingResponseMiddleware,
            $terminatedMiddleware,
        );

        $requestStruct  = $this->updateRequestStruct($method, $useMap, $namespace, $currentClass);
        $responseStruct = $this->updateResponseStruct($method, $useMap, $namespace, $currentClass);
        $parameters     = $isDynamic ? $this->updateParameters($args, $method, $useMap, $namespace, $currentClass) : [];

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
            requestStruct: $requestStruct,
            responseStruct: $responseStruct,
            isDynamic: $isDynamic,
            parameters: $parameters,
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
     * Resolve the handler from #[Route\RouteHandler] or fall back to [CurrentClass::class, methodName].
     *
     * @param array<string, string> $useMap
     */
    protected function updateHandler(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): HandlerData {
        foreach ($this->findAttributesOnNode($method, RouteHandler::class, $useMap, $namespace) as $attr) {
            $raw = $this->extractExprValue($this->getAttrArg($attr->args, 'handler', 0), $useMap, $namespace, $currentClass);

            if ($raw instanceof HandlerData) {
                return $raw;
            }
        }

        return new HandlerData(class: $currentClass, method: $method->name->toString());
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
            foreach ($attr->args as $i => $arg) {
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
        }

        return [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $sendingResponseMiddleware,
            $terminatedMiddleware,
        ];
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
        $parameters = [];

        // Inline parameters array (positional arg 2 on DynamicRoute)
        $inlineExpr = $this->getAttrArg($args, 'parameters', 2);

        if ($inlineExpr instanceof Array_) {
            foreach ($inlineExpr->items as $item) {
                if ($item === null) {
                    continue;
                }

                $param = $this->buildParameterFromExpr($item->value, $useMap, $namespace, $currentClass);

                if ($param !== null) {
                    $parameters[] = $param;
                }
            }
        }

        // #[Parameter] method-level attributes
        foreach ($this->findAttributesOnNode($method, Parameter::class, $useMap, $namespace) as $attr) {
            $param = $this->buildParameterData($attr->args, $useMap, $namespace, $currentClass);

            if ($param !== null) {
                $parameters[] = $param;
            }
        }

        // #[Parameter] attributes placed on PHP method parameters (e.g., #[Parameter(...)] string $value)
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
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildParameterData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): HttpParameterData|null {
        $name  = $this->extractExprValue($this->getAttrArg($args, 'name', 0), $useMap, $namespace, $currentClass);
        $regex = $this->extractExprValue($this->getAttrArg($args, 'regex', 1), $useMap, $namespace, $currentClass);

        if (! is_string($name) || $name === '' || ! is_string($regex)) {
            return null;
        }

        $castRaw        = $this->extractExprValue($this->getAttrArg($args, 'cast', 2), $useMap, $namespace, $currentClass);
        $isOptionalRaw  = $this->extractExprValue($this->getAttrArg($args, 'isOptional', 3), $useMap, $namespace, $currentClass);
        $shouldCapture  = $this->extractExprValue($this->getAttrArg($args, 'shouldCapture', 4), $useMap, $namespace, $currentClass);

        return new HttpParameterData(
            name: $name,
            regex: $regex,
            cast: is_string($castRaw) ? $castRaw : null,
            isOptional: is_bool($isOptionalRaw) ? $isOptionalRaw : false,
            shouldCapture: is_bool($shouldCapture) ? $shouldCapture : true,
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

        if ($data->requestStruct !== null) {
            $args[] = $this->buildNamedArg('requestStruct', $this->buildClassConstExpr($data->requestStruct));
        }

        if ($data->responseStruct !== null) {
            $args[] = $this->buildNamedArg('responseStruct', $this->buildClassConstExpr($data->responseStruct));
        }

        $targetClass = $data->isDynamic ? DynamicRouteModel::class : RouteModel::class;

        return $this->buildNewExpr($targetClass, $args);
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
