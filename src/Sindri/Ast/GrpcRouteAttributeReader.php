<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Ast;

use Override;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\RouteAttributeReader;
use Sindri\Ast\Contract\GrpcRouteAttributeReaderContract;
use Sindri\Ast\Data\GrpcRouteData;
use Sindri\Ast\Data\Result\GrpcRouteAttributeResult;
use Valkyrja\Grpc\Middleware\Contract\ResponseSentMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\SendingResponseMiddlewareContract;
use Valkyrja\Grpc\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Grpc\Routing\Attribute\Method;
use Valkyrja\Grpc\Routing\Attribute\Method\MethodHandler;
use Valkyrja\Grpc\Routing\Attribute\Method\Middleware;
use Valkyrja\Grpc\Routing\Attribute\Service;
use Valkyrja\Grpc\Routing\Data\Route;

use function array_push;
use function is_a;
use function is_string;

/**
 * Scans a gRPC service controller class file for #[Service], #[Method] and its sub-attributes and
 * returns PHP-Parser Expr nodes ready for the data cache generator.
 *
 * Mirrors the logic of the framework's runtime attribute collector but operates entirely on AST
 * without executing any PHP code — including the middleware classification cascade, so a class
 * serving several stages lands in all of their buckets exactly as it does at runtime.
 */
class GrpcRouteAttributeReader extends RouteAttributeReader implements GrpcRouteAttributeReaderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function readFile(string $filePath): GrpcRouteAttributeResult
    {
        $context = $this->parseClassFile($filePath);

        if ($context === null) {
            return new GrpcRouteAttributeResult();
        }

        [$class, $namespace, $useMap, $currentClass] = $context;

        $service = $this->readServiceName($class, $useMap, $namespace, $currentClass);

        // A class without a #[Service] attribute contributes no routes, even when its methods carry
        // #[Method] attributes: the service name is what keys them.
        if ($service === null) {
            return new GrpcRouteAttributeResult();
        }

        $routes = [];

        foreach ($class->getMethods() as $method) {
            foreach ($this->findAttributesOnNode($method, Method::class, $useMap, $namespace) as $attr) {
                $data = $this->buildRouteData($service, $attr->args, $method, $useMap, $namespace, $currentClass);

                if ($data !== null) {
                    $routes[$data->method] = $this->buildRouteExpr($data);
                }
            }
        }

        return new GrpcRouteAttributeResult(routes: $routes);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getRouteHandlerAttributeClass(): string
    {
        return MethodHandler::class;
    }

    /**
     * Read the class-level #[Service] name, or null when the class carries none.
     *
     * @param array<string, string> $useMap
     */
    protected function readServiceName(
        Class_ $class,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string|null {
        foreach ($this->findAttributesOnNode($class, Service::class, $useMap, $namespace) as $attr) {
            $service = $this->extractStringArg($attr->args, 'service', 0, $useMap, $namespace, $currentClass);

            if ($service !== '') {
                return $service;
            }
        }

        return null;
    }

    /**
     * Collect all attribute arguments for a #[Method] and its companions into a GrpcRouteData.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildRouteData(
        string $service,
        array $args,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): GrpcRouteData|null {
        $name = $this->extractStringArg($args, 'name', 0, $useMap, $namespace, $currentClass);

        if ($name === '') {
            return null;
        }

        [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $sendingResponseMiddleware,
            $responseSentMiddleware,
        ] = $this->updateMiddleware($method, $useMap, $namespace, $currentClass);

        return new GrpcRouteData(
            method: "/$service/$name",
            handler: $this->updateHandler($method, $useMap, $namespace, $currentClass),
            requestType: $this->extractClassArg($args, 'requestType', 3, $useMap, $namespace, $currentClass),
            responseType: $this->extractClassArg($args, 'responseType', 4, $useMap, $namespace, $currentClass),
            clientStreaming: $this->extractBoolArg($args, 'clientStreaming', 1, $useMap, $namespace, $currentClass),
            serverStreaming: $this->extractBoolArg($args, 'serverStreaming', 2, $useMap, $namespace, $currentClass),
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            sendingResponseMiddleware: $sendingResponseMiddleware,
            responseSentMiddleware: $responseSentMiddleware,
        );
    }

    /**
     * Extract a single class-string attribute argument, or null when absent.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return class-string|null
     */
    protected function extractClassArg(
        array $args,
        string $name,
        int $position,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string|null {
        $value = $this->extractExprValue($this->getAttrArg($args, $name, $position), $useMap, $namespace, $currentClass);

        if (! is_string($value) || $value === '') {
            return null;
        }

        /** @var class-string $value */
        return $value;
    }

    /**
     * Collect and classify #[Method\Middleware] attributes into the five stage lists.
     *
     * @param array<string, string> $useMap
     *
     * @return array{class-string[], class-string[], class-string[], class-string[], class-string[]}
     */
    protected function updateMiddleware(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $routeMatchedMiddleware    = [];
        $routeDispatchedMiddleware = [];
        $throwableCaughtMiddleware = [];
        $sendingResponseMiddleware = [];
        $responseSentMiddleware    = [];

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
                $responseSentMiddleware,
            ] = $this->classifyMiddleware(
                $mwFqn,
                $routeMatchedMiddleware,
                $routeDispatchedMiddleware,
                $throwableCaughtMiddleware,
                $sendingResponseMiddleware,
                $responseSentMiddleware,
            );
        }

        return [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $sendingResponseMiddleware,
            $responseSentMiddleware,
        ];
    }

    /**
     * Classify a single middleware FQN into every stage list whose contract it implements.
     *
     * The checks are independent — never an if/else cascade — so a middleware serving several
     * stages lands in all of their buckets, and middleware is appended, never deduplicated.
     *
     * @param class-string[] $routeMatchedMiddleware
     * @param class-string[] $routeDispatchedMiddleware
     * @param class-string[] $throwableCaughtMiddleware
     * @param class-string[] $sendingResponseMiddleware
     * @param class-string[] $responseSentMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[], class-string[]}
     */
    protected function classifyMiddleware(
        string $mwFqn,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $sendingResponseMiddleware,
        array $responseSentMiddleware,
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

        if (is_a($mwFqn, ResponseSentMiddlewareContract::class, true)) {
            $responseSentMiddleware[] = $mwFqn;
        }

        return [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $sendingResponseMiddleware,
            $responseSentMiddleware,
        ];
    }

    /**
     * Convert a GrpcRouteData into a PHP-Parser New_ expression for the framework's Route.
     */
    protected function buildRouteExpr(GrpcRouteData $data): Expr
    {
        $args = [
            $this->buildNamedArg('method', $this->buildStringExpr($data->method)),
        ];

        if ($data->handler !== null) {
            $args[] = $this->buildNamedArg('handler', $this->buildHandlerExpr($data->handler));
        }

        if ($data->requestType !== null) {
            $args[] = $this->buildNamedArg('requestType', $this->buildClassConstExpr($data->requestType));
        }

        if ($data->responseType !== null) {
            $args[] = $this->buildNamedArg('responseType', $this->buildClassConstExpr($data->responseType));
        }

        if ($data->clientStreaming) {
            $args[] = $this->buildNamedArg('clientStreaming', $this->buildBoolExpr(true));
        }

        if ($data->serverStreaming) {
            $args[] = $this->buildNamedArg('serverStreaming', $this->buildBoolExpr(true));
        }

        array_push($args, ...$this->buildRouteMiddlewareArgs($data));

        return $this->buildNewExpr(Route::class, $args);
    }

    /**
     * Build the per-stage middleware named-arg list for a GrpcRouteData.
     *
     * @return Arg[]
     */
    protected function buildRouteMiddlewareArgs(GrpcRouteData $data): array
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

        if ($data->responseSentMiddleware !== []) {
            $args[] = $this->buildNamedArg('responseSentMiddleware', $this->buildClassArrayExpr($data->responseSentMiddleware));
        }

        return $args;
    }
}
