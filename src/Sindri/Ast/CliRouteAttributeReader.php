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

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name as PhpParserName;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\CliRouteAttributeReaderContract;
use Sindri\Ast\Data\CliArgumentParameterData;
use Sindri\Ast\Data\CliOptionParameterData;
use Sindri\Ast\Data\CliRouteData;
use Sindri\Ast\Data\HandlerData;
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

/**
 * Scans a CLI controller class file for #[Route] and related sub-attributes and
 * returns PHP-Parser Expr nodes ready for the data cache generator.
 *
 * Mirrors the logic of the framework's runtime attribute collector but operates
 * entirely on AST without executing any PHP code.
 */
class CliRouteAttributeReader extends AstReader implements CliRouteAttributeReaderContract
{
    public function readFile(string $filePath): CliRouteAttributeResult
    {
        $stmts = $this->parseFileToStmts($filePath);

        [$namespace, $innerStmts] = $this->unwrapNamespace($stmts);

        $useMap = $this->buildUseMap($innerStmts);
        $class  = $this->findClass($innerStmts);

        if ($class === null) {
            return new CliRouteAttributeResult();
        }

        $currentClass = $namespace !== ''
            ? $namespace . '\\' . ($class->name?->toString() ?? '')
            : ($class->name?->toString() ?? '');

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

    /**
     * Collect all attribute arguments for a #[Route] and its companions into a CliRouteData.
     *
     * @param \PhpParser\Node\Arg[] $args
     * @param array<string, string> $useMap
     */
    protected function buildRouteData(
        array $args,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): CliRouteData|null {
        $name        = $this->extractExprValue($this->getAttrArg($args, 'name', 0), $useMap, $namespace, $currentClass);
        $description = $this->extractExprValue($this->getAttrArg($args, 'description', 1), $useMap, $namespace, $currentClass);

        if (! is_string($name) || $name === '' || ! is_string($description)) {
            return null;
        }

        $name = $this->updateName($name, $method, $useMap, $namespace, $currentClass);

        $routeMatchedMiddleware    = [];
        $routeDispatchedMiddleware = [];
        $throwableCaughtMiddleware = [];
        $exitedMiddleware          = [];

        $matchedExpr    = $this->getAttrArg($args, 'routeMatchedMiddleware', 4);
        $dispatchedExpr = $this->getAttrArg($args, 'routeDispatchedMiddleware', 5);
        $throwableExpr  = $this->getAttrArg($args, 'throwableCaughtMiddleware', 6);
        $exitedExpr     = $this->getAttrArg($args, 'exitedMiddleware', 7);

        if ($matchedExpr instanceof Array_) {
            $routeMatchedMiddleware = $this->extractClassListFromArrayExpr($matchedExpr, $useMap, $namespace, $currentClass);
        }

        if ($dispatchedExpr instanceof Array_) {
            $routeDispatchedMiddleware = $this->extractClassListFromArrayExpr($dispatchedExpr, $useMap, $namespace, $currentClass);
        }

        if ($throwableExpr instanceof Array_) {
            $throwableCaughtMiddleware = $this->extractClassListFromArrayExpr($throwableExpr, $useMap, $namespace, $currentClass);
        }

        if ($exitedExpr instanceof Array_) {
            $exitedMiddleware = $this->extractClassListFromArrayExpr($exitedExpr, $useMap, $namespace, $currentClass);
        }

        [$routeMatchedMiddleware, $routeDispatchedMiddleware, $throwableCaughtMiddleware, $exitedMiddleware]
            = $this->updateMiddleware(
                $method,
                $useMap,
                $namespace,
                $currentClass,
                $routeMatchedMiddleware,
                $routeDispatchedMiddleware,
                $throwableCaughtMiddleware,
                $exitedMiddleware,
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
     * @param \PhpParser\Node\Arg[] $args
     * @param array<string, string> $useMap
     */
    protected function buildArgumentData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): CliArgumentParameterData|null {
        $name        = $this->extractExprValue($this->getAttrArg($args, 'name', 0), $useMap, $namespace, $currentClass);
        $description = $this->extractExprValue($this->getAttrArg($args, 'description', 1), $useMap, $namespace, $currentClass);

        if (! is_string($name) || $name === '' || ! is_string($description)) {
            return null;
        }

        $castRaw      = $this->extractExprValue($this->getAttrArg($args, 'cast', 2), $useMap, $namespace, $currentClass);
        $modeRaw      = $this->extractExprValue($this->getAttrArg($args, 'mode', 3), $useMap, $namespace, $currentClass);
        $valueModeRaw = $this->extractExprValue($this->getAttrArg($args, 'valueMode', 4), $useMap, $namespace, $currentClass);

        return new CliArgumentParameterData(
            name: $name,
            description: $description,
            cast: is_string($castRaw) ? $castRaw : null,
            mode: is_string($modeRaw) ? $modeRaw : ArgumentMode::class . '::' . ArgumentMode::OPTIONAL->name,
            valueMode: is_string($valueModeRaw) ? $valueModeRaw : ArgumentValueMode::class . '::' . ArgumentValueMode::DEFAULT->name,
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
     * @param \PhpParser\Node\Arg[] $args
     * @param array<string, string> $useMap
     */
    protected function buildOptionData(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): CliOptionParameterData|null {
        $name        = $this->extractExprValue($this->getAttrArg($args, 'name', 0), $useMap, $namespace, $currentClass);
        $description = $this->extractExprValue($this->getAttrArg($args, 'description', 1), $useMap, $namespace, $currentClass);

        if (! is_string($name) || $name === '' || ! is_string($description)) {
            return null;
        }

        $valueDisplayName = $this->extractExprValue($this->getAttrArg($args, 'valueDisplayName', 2), $useMap, $namespace, $currentClass);
        $castRaw          = $this->extractExprValue($this->getAttrArg($args, 'cast', 3), $useMap, $namespace, $currentClass);
        $defaultValue     = $this->extractExprValue($this->getAttrArg($args, 'defaultValue', 4), $useMap, $namespace, $currentClass);

        $shortNamesExpr  = $this->getAttrArg($args, 'shortNames', 5);
        $validValuesExpr = $this->getAttrArg($args, 'validValues', 6);
        $modeRaw         = $this->extractExprValue($this->getAttrArg($args, 'mode', 7), $useMap, $namespace, $currentClass);
        $valueModeRaw    = $this->extractExprValue($this->getAttrArg($args, 'valueMode', 8), $useMap, $namespace, $currentClass);

        $shortNames  = $shortNamesExpr instanceof Array_  ? $this->extractStringListFromArrayExpr($shortNamesExpr, $useMap, $namespace, $currentClass)  : [];
        $validValues = $validValuesExpr instanceof Array_ ? $this->extractStringListFromArrayExpr($validValuesExpr, $useMap, $namespace, $currentClass) : [];

        return new CliOptionParameterData(
            name: $name,
            description: $description,
            valueDisplayName: is_string($valueDisplayName) ? $valueDisplayName : '',
            cast: is_string($castRaw) ? $castRaw : null,
            defaultValue: is_string($defaultValue) ? $defaultValue : '',
            shortNames: $shortNames,
            validValues: $validValues,
            mode: is_string($modeRaw) ? $modeRaw : OptionMode::class . '::' . OptionMode::OPTIONAL->name,
            valueMode: is_string($valueModeRaw) ? $valueModeRaw : OptionValueMode::class . '::' . OptionValueMode::DEFAULT->name,
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

        return $this->buildNewExpr(RouteModel::class, $args);
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
                $data->cast !== null ? $this->buildEnumCaseExpr($data->cast) : new ConstFetch(new PhpParserName('null'))
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
                $data->cast !== null ? $this->buildEnumCaseExpr($data->cast) : new ConstFetch(new PhpParserName('null'))
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

    /**
     * Extract a list of string scalar values from an array expression.
     *
     * @param array<string, string> $useMap
     *
     * @return string[]
     */
    protected function extractStringListFromArrayExpr(
        Array_ $array,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array {
        $values = [];

        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }

            $value = $this->extractExprValue($item->value, $useMap, $namespace, $currentClass);

            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

}