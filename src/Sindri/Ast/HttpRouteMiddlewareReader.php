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
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\HttpRouteMiddlewareReaderContract;
use Sindri\Ast\Data\HttpRouteData;
use Valkyrja\Http\Message\Enum\RequestMethod as RequestMethodEnum;
use Valkyrja\Http\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\TerminatedMiddlewareContract;
use Valkyrja\Http\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod;
use Valkyrja\Http\Routing\Attribute\Route\RequestStruct;
use Valkyrja\Http\Routing\Attribute\Route\ResponseStruct;

use function is_a;
use function is_string;

/**
 * Reads middleware, request methods, and struct attributes for HTTP routes, and builds
 * their corresponding AST expressions.
 *
 * Extracted from HttpRouteAttributeReader to keep each class under the
 * complexity threshold; injected as a constructor argument.
 */
class HttpRouteMiddlewareReader extends AstReader implements HttpRouteMiddlewareReaderContract
{
    #[Override]
    public function extractInlineRequestMethods(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $requestMethodsExpr = $this->getAttrArg($args, 'requestMethods', 3);

        if (! $requestMethodsExpr instanceof Array_) {
            return [];
        }

        return $this->extractClassListFromArrayExpr($requestMethodsExpr, $useMap, $namespace, $currentClass);
    }

    #[Override]
    public function updateRequestMethods(
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

    #[Override]
    public function updateMiddleware(
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

    #[Override]
    public function updateRequestStruct(
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

    #[Override]
    public function updateResponseStruct(
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

    #[Override]
    public function buildRouteMiddlewareArgs(HttpRouteData $data): array
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

    #[Override]
    public function buildRouteStructArgs(HttpRouteData $data): array
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
}