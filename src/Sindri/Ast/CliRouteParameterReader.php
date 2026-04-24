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
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\CliRouteParameterReaderContract;
use Sindri\Ast\Data\CliArgumentParameterData;
use Sindri\Ast\Data\CliOptionParameterData;
use Sindri\Ast\Data\CliRouteData;
use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Data\ArgumentParameter as ArgumentParameterModel;
use Valkyrja\Cli\Routing\Data\OptionParameter as OptionParameterModel;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;
use Valkyrja\Cli\Routing\Enum\ArgumentValueMode;
use Valkyrja\Cli\Routing\Enum\OptionMode;
use Valkyrja\Cli\Routing\Enum\OptionValueMode;

/**
 * Builds AST expressions for CLI route argument and option parameters.
 *
 * Extracted from CliRouteAttributeReader to keep each class under the
 * complexity threshold; injected as a constructor argument.
 */
class CliRouteParameterReader extends AstReader implements CliRouteParameterReaderContract
{
    /**
     * Build the arguments/options named-arg list for a CliRouteData.
     *
     * @return Arg[]
     */
    #[Override]
    public function buildParameterArgs(CliRouteData $data): array
    {
        $args = [];

        if ($data->arguments !== []) {
            $args[] = $this->buildNamedArg('arguments', $this->buildArgumentListExpr($data->arguments));
        }

        if ($data->options !== []) {
            $args[] = $this->buildNamedArg('options', $this->buildOptionListExpr($data->options));
        }

        return $args;
    }

    /**
     * Collect all #[ArgumentParameter] attributes from the method.
     *
     * @param array<string, string> $useMap
     *
     * @return CliArgumentParameterData[]
     */
    #[Override]
    public function updateArguments(
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
     * Collect all #[OptionParameter] attributes from the method.
     *
     * @param array<string, string> $useMap
     *
     * @return CliOptionParameterData[]
     */
    #[Override]
    public function updateOptions(
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
