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
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\RouteAttributeReader;
use Sindri\Ast\Contract\CliRouteAttributeReaderContract;
use Sindri\Ast\Data\CliArgumentParameterData;
use Sindri\Ast\Data\CliOptionParameterData;
use Sindri\Ast\Data\CliRouteData;
use Sindri\Ast\Result\CliRouteAttributeResult;
use Valkyrja\Cli\Middleware\Contract\ExitedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteDispatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\RouteMatchedMiddlewareContract;
use Valkyrja\Cli\Middleware\Contract\ThrowableCaughtMiddlewareContract;
use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;
use Valkyrja\Cli\Routing\Attribute\Route\Name as RouteName;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Data\ArgumentParameter as ArgumentParameterModel;
use Valkyrja\Cli\Routing\Data\OptionParameter as OptionParameterModel;
use Valkyrja\Cli\Routing\Data\Route as RouteModel;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;

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
            arguments: $this->updateArguments($method, $useMap, $namespace, $currentClass),
            options: $this->updateOptions($method, $useMap, $namespace, $currentClass),
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
        foreach ($this->findAttributesOnNode($method, RouteName::class, $useMap, $namespace) as $attr) {
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
     * Collect all #[ArgumentParameter] attributes from the method.
     *
     * @param array<string, string> $useMap
     *
     * @return CliArgumentParameterData[]
     */
    protected function updateArguments(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $arguments = [];

        foreach ($this->findAttributesOnNode($method, ArgumentParameter::class, $useMap, $namespace) as $attr) {
            $data = $this->buildArgumentData($attr->args, $useMap, $namespace, $currentClass);

            if ($data !== null) {
                $arguments[] = $data;
            }
        }

        return $arguments;
    }

    /**
     * Build a CliArgumentParameterData from a #[ArgumentParameter] attribute.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildArgumentData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): CliArgumentParameterData|null {
        $name        = $this->extractStringArg($args, 'name', 0, $useMap, $namespace, $currentClass);
        $description = $this->extractStringArg($args, 'description', 1, $useMap, $namespace, $currentClass);

        if ($name === '' || $description === '') {
            return null;
        }

        return new CliArgumentParameterData(
            name: $name,
            description: $description,
            cast: $this->extractStringArg($args, 'cast', 2, $useMap, $namespace, $currentClass) ?: null,
            mode: $this->extractStringArg($args, 'mode', 3, $useMap, $namespace, $currentClass, ArgumentMode::class . '::' . ArgumentMode::OPTIONAL->name),
            valueMode: $this->extractStringArg($args, 'valueMode', 4, $useMap, $namespace, $currentClass, ArgumentValueMode::class . '::' . ArgumentValueMode::DEFAULT->name),
        );
    }

    /**
     * Collect all #[OptionParameter] attributes from the method.
     *
     * @param array<string, string> $useMap
     *
     * @return CliOptionParameterData[]
     */
    protected function updateOptions(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $options = [];

        foreach ($this->findAttributesOnNode($method, OptionParameter::class, $useMap, $namespace) as $attr) {
            $data = $this->buildOptionData($attr->args, $useMap, $namespace, $currentClass);

            if ($data !== null) {
                $options[] = $data;
            }
        }

        return $options;
    }

    /**
     * Build a CliOptionParameterData from a #[OptionParameter] attribute.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     */
    protected function buildOptionData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): CliOptionParameterData|null {
        $name        = $this->extractStringArg($args, 'name', 0, $useMap, $namespace, $currentClass);
        $description = $this->extractStringArg($args, 'description', 1, $useMap, $namespace, $currentClass);

        if ($name === '' || $description === '') {
            return null;
        }

        return new CliOptionParameterData(
            name: $name,
            description: $description,
            valueDisplayName: $this->extractStringArg($args, 'valueDisplayName', 2, $useMap, $namespace, $currentClass),
            cast: $this->extractStringArg($args, 'cast', 3, $useMap, $namespace, $currentClass) ?: null,
            defaultValue: $this->extractStringArg($args, 'defaultValue', 4, $useMap, $namespace, $currentClass),
            shortNames: $this->extractStringListArg($args, 'shortNames', 5, $useMap, $namespace, $currentClass),
            validValues: $this->extractStringListArg($args, 'validValues', 6, $useMap, $namespace, $currentClass),
            mode: $this->extractStringArg($args, 'mode', 7, $useMap, $namespace, $currentClass, OptionMode::class . '::' . OptionMode::OPTIONAL->name),
            valueMode: $this->extractStringArg($args, 'valueMode', 8, $useMap, $namespace, $currentClass, OptionValueMode::class . '::' . OptionValueMode::DEFAULT->name),
        );
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

        if ($data->arguments !== []) {
            $args[] = $this->buildNamedArg('arguments', $this->buildArgumentListExpr($data->arguments));
        }

        if ($data->options !== []) {
            $args[] = $this->buildNamedArg('options', $this->buildOptionListExpr($data->options));
        }

        return $args;
    }

    /**
     * Build an array expression of ArgumentParameter New_ nodes.
     *
     * @param CliArgumentParameterData[] $arguments
     */
    protected function buildArgumentListExpr(array $arguments): Array_
    {
        $items = [];

        foreach ($arguments as $argument) {
            $items[] = new ArrayItem($this->buildArgumentExpr($argument));
        }

        return new Array_($items);
    }

    /**
     * Convert a CliArgumentParameterData into a New_ expression for ArgumentParameter.
     */
    protected function buildArgumentExpr(CliArgumentParameterData $data): Expr
    {
        $args = [
            $this->buildNamedArg('name', $this->buildStringExpr($data->name)),
            $this->buildNamedArg('description', $this->buildStringExpr($data->description)),
            $this->buildNamedArg(
                'cast',
                $data->cast !== null ? $this->buildEnumCaseExpr($data->cast) : new ConstFetch(new Name('null'))
            ),
            $this->buildNamedArg('mode', $this->buildEnumCaseExpr($data->mode)),
            $this->buildNamedArg('valueMode', $this->buildEnumCaseExpr($data->valueMode)),
        ];

        return $this->buildNewExpr(ArgumentParameterModel::class, $args);
    }

    /**
     * Build an array expression of OptionParameter New_ nodes.
     *
     * @param CliOptionParameterData[] $options
     */
    protected function buildOptionListExpr(array $options): Array_
    {
        $items = [];

        foreach ($options as $option) {
            $items[] = new ArrayItem($this->buildOptionExpr($option));
        }

        return new Array_($items);
    }

    /**
     * Convert a CliOptionParameterData into a New_ expression for OptionParameter.
     */
    protected function buildOptionExpr(CliOptionParameterData $data): Expr
    {
        $args = [
            $this->buildNamedArg('name', $this->buildStringExpr($data->name)),
            $this->buildNamedArg('description', $this->buildStringExpr($data->description)),
            $this->buildNamedArg('valueDisplayName', $this->buildStringExpr($data->valueDisplayName)),
            $this->buildNamedArg(
                'cast',
                $data->cast !== null ? $this->buildEnumCaseExpr($data->cast) : new ConstFetch(new Name('null'))
            ),
            $this->buildNamedArg('defaultValue', $this->buildStringExpr($data->defaultValue)),
        ];

        if ($data->shortNames !== []) {
            $args[] = $this->buildNamedArg('shortNames', $this->buildStringArrayExpr($data->shortNames));
        }

        if ($data->validValues !== []) {
            $args[] = $this->buildNamedArg('validValues', $this->buildStringArrayExpr($data->validValues));
        }

        $args[] = $this->buildNamedArg('mode', $this->buildEnumCaseExpr($data->mode));
        $args[] = $this->buildNamedArg('valueMode', $this->buildEnumCaseExpr($data->valueMode));

        return $this->buildNewExpr(OptionParameterModel::class, $args);
    }
}
