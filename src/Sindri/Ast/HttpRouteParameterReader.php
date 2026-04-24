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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\VariadicPlaceholder;
use Sindri\Ast\Abstract\AstReader;
use Sindri\Ast\Contract\HttpRouteParameterReaderContract;
use Sindri\Ast\Data\HttpParameterData;
use Valkyrja\Http\Routing\Attribute\Parameter;
use Valkyrja\Http\Routing\Data\Parameter as ParameterModel;

/**
 * Reads and builds AST expressions for HTTP dynamic route parameters.
 *
 * Extracted from HttpRouteAttributeReader to keep each class under the
 * complexity threshold; injected as a constructor argument.
 */
class HttpRouteParameterReader extends AstReader implements HttpRouteParameterReaderContract
{
    #[Override]
    public function updateParameters(
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

    #[Override]
    public function buildParameterListExpr(array $parameters): Array_
    {
        $items = [];

        foreach ($parameters as $parameter) {
            $items[] = new ArrayItem($this->buildParameterExpr($parameter));
        }

        return new Array_($items);
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
