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
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\RouteAttributeReader;
use Sindri\Ast\Contract\QueueRouteAttributeReaderContract;
use Sindri\Ast\Data\QueueRouteData;
use Sindri\Ast\Data\Result\QueueRouteAttributeResult;
use Valkyrja\Queue\Middleware\Contract\ResultSettledMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\SettlingResultMiddlewareContract;
use Valkyrja\Queue\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Queue\Routing\Attribute\Route;
use Valkyrja\Queue\Routing\Attribute\Route\Middleware;
use Valkyrja\Queue\Routing\Attribute\Route\Name;
use Valkyrja\Queue\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Queue\Routing\Data\Route as RouteModel;

use function array_push;
use function is_a;
use function is_string;

class QueueRouteAttributeReader extends RouteAttributeReader implements QueueRouteAttributeReaderContract
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function readFile(string $filePath): QueueRouteAttributeResult
    {
        $context = $this->parseClassFile($filePath);

        if ($context === null) {
            return new QueueRouteAttributeResult();
        }

        [$class, $namespace, $useMap, $currentClass] = $context;

        $routes = [];

        foreach ($class->getMethods() as $method) {
            foreach ($this->findAttributesOnNode($method, Route::class, $useMap, $namespace) as $attr) {
                $data = $this->buildRouteData($attr->args, $method, $useMap, $namespace, $currentClass);

                if ($data !== null) {
                    $routes[$data->name] = $this->buildRouteExpr($data);
                }
            }
        }

        return new QueueRouteAttributeResult(routes: $routes);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getRouteHandlerAttributeClass(): string
    {
        return RouteHandler::class;
    }

    /**
     * Collect all attribute arguments for a #[Route] and its companions into a QueueRouteData.
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
    ): QueueRouteData|null {
        $name        = $this->extractStringArg($args, 'name', 0, $useMap, $namespace, $currentClass);
        $description = $this->extractStringArg($args, 'description', 1, $useMap, $namespace, $currentClass);

        if ($name === '' || $description === '') {
            return null;
        }

        $name = $this->updateName($name, $method, $useMap, $namespace, $currentClass);

        [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $settlingResultMiddleware,
            $resultSettledMiddleware,
        ] = $this->updateMiddleware(
            $method,
            $useMap,
            $namespace,
            $currentClass,
            $this->extractClassListArg($args, 'routeMatchedMiddleware', 3, $useMap, $namespace, $currentClass),
            $this->extractClassListArg($args, 'routeDispatchedMiddleware', 4, $useMap, $namespace, $currentClass),
            $this->extractClassListArg($args, 'throwableCaughtMiddleware', 5, $useMap, $namespace, $currentClass),
            $this->extractClassListArg($args, 'settlingResultMiddleware', 6, $useMap, $namespace, $currentClass),
            $this->extractClassListArg($args, 'resultSettledMiddleware', 7, $useMap, $namespace, $currentClass),
        );

        return new QueueRouteData(
            name: $name,
            description: $description,
            handler: $this->updateHandler($method, $useMap, $namespace, $currentClass),
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            settlingResultMiddleware: $settlingResultMiddleware,
            resultSettledMiddleware: $resultSettledMiddleware,
        );
    }

    /**
     * Apply #[Route\Name] overrides to the job name.
     *
     * A class-level name prefixes and a method-level name suffixes, which is
     * how the runtime collector composes them.
     *
     * @param array<string, string> $useMap
     */
    protected function updateName(
        string $name,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string {
        foreach ($this->findAttributesOnNode($method, Name::class, $useMap, $namespace) as $attr) {
            $override = $this->extractExprValue($this->getAttrArg($attr->args, 'value', 0), $useMap, $namespace, $currentClass);

            if (is_string($override) && $override !== '') {
                $name = $override;
            }
        }

        return $name;
    }

    /**
     * Collect and classify #[Route\Middleware] attributes into the five middleware lists.
     *
     * @param array<string, string> $useMap
     * @param class-string[]        $routeMatchedMiddleware
     * @param class-string[]        $routeDispatchedMiddleware
     * @param class-string[]        $throwableCaughtMiddleware
     * @param class-string[]        $settlingResultMiddleware
     * @param class-string[]        $resultSettledMiddleware
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
        array $settlingResultMiddleware,
        array $resultSettledMiddleware,
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
                $settlingResultMiddleware,
                $resultSettledMiddleware,
            ] = $this->classifyMiddleware(
                $mwFqn,
                $routeMatchedMiddleware,
                $routeDispatchedMiddleware,
                $throwableCaughtMiddleware,
                $settlingResultMiddleware,
                $resultSettledMiddleware,
            );
        }

        return [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $settlingResultMiddleware,
            $resultSettledMiddleware,
        ];
    }

    /**
     * Classify a single middleware FQN into every list whose contract it implements.
     *
     * @param class-string[] $routeMatchedMiddleware
     * @param class-string[] $routeDispatchedMiddleware
     * @param class-string[] $throwableCaughtMiddleware
     * @param class-string[] $settlingResultMiddleware
     * @param class-string[] $resultSettledMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[], class-string[]}
     */
    protected function classifyMiddleware(
        string $mwFqn,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $settlingResultMiddleware,
        array $resultSettledMiddleware,
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

        if (is_a($mwFqn, SettlingResultMiddlewareContract::class, true)) {
            $settlingResultMiddleware[] = $mwFqn;
        }

        if (is_a($mwFqn, ResultSettledMiddlewareContract::class, true)) {
            $resultSettledMiddleware[] = $mwFqn;
        }

        return [
            $routeMatchedMiddleware,
            $routeDispatchedMiddleware,
            $throwableCaughtMiddleware,
            $settlingResultMiddleware,
            $resultSettledMiddleware,
        ];
    }

    /**
     * Convert a QueueRouteData into a PHP-Parser New_ expression for the framework route.
     */
    protected function buildRouteExpr(QueueRouteData $data): Expr
    {
        $args = [
            $this->buildNamedArg('name', $this->buildEnumCaseExpr($data->name)),
            $this->buildNamedArg('description', $this->buildStringExpr($data->description)),
        ];

        if ($data->handler !== null) {
            $args[] = $this->buildNamedArg('handler', $this->buildHandlerExpr($data->handler));
        }

        array_push($args, ...$this->buildRouteMiddlewareArgs($data));

        return $this->buildNewExpr(RouteModel::class, $args);
    }

    /**
     * Build the middleware named-arg list for a QueueRouteData.
     *
     * @return Arg[]
     */
    protected function buildRouteMiddlewareArgs(QueueRouteData $data): array
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

        if ($data->settlingResultMiddleware !== []) {
            $args[] = $this->buildNamedArg('settlingResultMiddleware', $this->buildClassArrayExpr($data->settlingResultMiddleware));
        }

        if ($data->resultSettledMiddleware !== []) {
            $args[] = $this->buildNamedArg('resultSettledMiddleware', $this->buildClassArrayExpr($data->resultSettledMiddleware));
        }

        return $args;
    }
}
