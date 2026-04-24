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
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\RouteAttributeReader;
use Sindri\Ast\Contract\CliRouteAttributeReaderContract;
use Sindri\Ast\Contract\CliRouteParameterReaderContract;
use Sindri\Ast\Data\CliRouteData;
use Sindri\Ast\Result\CliRouteAttributeResult;
use Valkyrja\Cli\Middleware\Contract\ExitedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;
use Valkyrja\Cli\Routing\Attribute\Route\Name;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Data\Route as RouteModel;

use function is_a;
use function is_string;

/**
 * Scans a CLI controller class file for #[Route] and related sub-attributes and
 * returns PHP-Parser Expr nodes ready for the data cache generator.
 *
 * Mirrors the logic of the framework's runtime attribute collector but operates
 * entirely on AST without executing any PHP code.
 */
class CliRouteAttributeReader extends RouteAttributeReader implements CliRouteAttributeReaderContract
{
    public function __construct(
        private readonly CliRouteParameterReaderContract $parameterReader = new CliRouteParameterReader(),
    ) {
    }

    #[Override]
    public function readFile(string $filePath): CliRouteAttributeResult
    {
        $context = $this->parseClassFile($filePath);

        if ($context === null) {
            return new CliRouteAttributeResult();
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

        return new CliRouteAttributeResult(routes: $routes);
    }

    #[Override]
    protected function getRouteHandlerAttributeClass(): string
    {
        return RouteHandler::class;
    }

    /**
     * Collect all attribute arguments for a #[Route] and its companions into a CliRouteData.
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
    ): CliRouteData|null {
        $name        = $this->extractStringArg($args, 'name', 0, $useMap, $namespace, $currentClass);
        $description = $this->extractStringArg($args, 'description', 1, $useMap, $namespace, $currentClass);

        if ($name === '' || $description === '') {
            return null;
        }

        $name = $this->updateName($name, $method, $useMap, $namespace, $currentClass);

        [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $exitedMiddleware]
            = $this->updateMiddleware(
                $method,
                $useMap,
                $namespace,
                $currentClass,
                $this->extractClassListArg($args, 'routeMatchedMiddleware', 4, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'routeDispatchedMiddleware', 5, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'throwableCaughtMiddleware', 6, $useMap, $namespace, $currentClass),
                $this->extractClassListArg($args, 'exitedMiddleware', 7, $useMap, $namespace, $currentClass),
            );

        return new CliRouteData(
            name: $name,
            description: $description,
            handler: $this->updateHandler($method, $useMap, $namespace, $currentClass),
            helpText: null,
            routeMatchedMiddleware: $routeMatchedMiddleware,
            routeDispatchedMiddleware: $routeDispatchedMiddleware,
            throwableCaughtMiddleware: $throwableCaughtMiddleware,
            exitedMiddleware: $exitedMiddleware,
            arguments: $this->parameterReader->updateArguments($method, $useMap, $namespace, $currentClass),
            options: $this->parameterReader->updateOptions($method, $useMap, $namespace, $currentClass),
        );
    }

    /**
     * Apply #[Route\Name] overrides to the route name.
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
     * Collect and classify #[Route\Middleware] attributes into the four middleware lists.
     *
     * @param array<string, string> $useMap
     * @param class-string[]        $routeMatchedMiddleware
     * @param class-string[]        $routeDispatchedMiddleware
     * @param class-string[]        $throwableCaughtMiddleware
     * @param class-string[]        $exitedMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[]}
     */
    protected function updateMiddleware(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $exitedMiddleware,
    ): array {
        foreach ($this->findAttributesOnNode($method, Middleware::class, $useMap, $namespace) as $attr) {
            $mwFqn = $this->extractExprValue($this->getAttrArg($attr->args, 'name', 0), $useMap, $namespace, $currentClass);

            if (! is_string($mwFqn) || $mwFqn === '') {
                continue;
            }

            [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $exitedMiddleware]
                = $this->classifyMiddleware($mwFqn, $routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $exitedMiddleware);
        }

        return [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $exitedMiddleware];
    }

    /**
     * Classify a single middleware FQN into the appropriate list(s) based on implemented contracts.
     *
     * @param class-string[] $routeMatchedMiddleware
     * @param class-string[] $routeDispatchedMiddleware
     * @param class-string[] $throwableCaughtMiddleware
     * @param class-string[] $exitedMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[]}
     */
    protected function classifyMiddleware(
        string $mwFqn,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $exitedMiddleware,
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

        if (is_a($mwFqn, ExitedMiddlewareContract::class, true)) {
            $exitedMiddleware[] = $mwFqn;
        }

        return [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $exitedMiddleware];
    }

    /**
     * Convert a CliRouteData into a PHP-Parser New_ expression for Valkyrja\Cli\Routing\Data\Route.
     */
    protected function buildRouteExpr(CliRouteData $data): Expr
    {
        $args = [
            $this->buildNamedArg('name', $this->buildStringExpr($data->name)),
            $this->buildNamedArg('description', $this->buildStringExpr($data->description)),
        ];

        if ($data->handler !== null) {
            $args[] = $this->buildNamedArg('handler', $this->buildHandlerExpr($data->handler));
        }

        if ($data->helpText !== null) {
            $args[] = $this->buildNamedArg('helpText', $this->buildHandlerExpr($data->helpText));
        }

        array_push($args, ...$this->buildRouteMiddlewareArgs($data));

        return $this->buildNewExpr(RouteModel::class, $args);
    }

    /**
     * Build the middleware/arguments/options named-arg list for a CliRouteData.
     *
     * @return Arg[]
     */
    protected function buildRouteMiddlewareArgs(CliRouteData $data): array
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

        if ($data->exitedMiddleware !== []) {
            $args[] = $this->buildNamedArg('exitedMiddleware', $this->buildClassArrayExpr($data->exitedMiddleware));
        }

        array_push($args, ...$this->parameterReader->buildParameterArgs($data));

        return $args;
    }
}
