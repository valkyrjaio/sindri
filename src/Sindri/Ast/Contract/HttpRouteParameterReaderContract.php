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

namespace Sindri\Ast\Contract;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Data\HttpParameterData;

/**
 * Reads and builds AST expressions for HTTP dynamic route parameters.
 */
interface HttpRouteParameterReaderContract
{
    /**
     * Collect parameters from inline DynamicRoute args and #[Parameter] attributes.
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return HttpParameterData[]
     */
    public function updateParameters(
        array $args,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array;

    /**
     * Build an array expression of Parameter New_ nodes.
     *
     * @param HttpParameterData[] $parameters
     */
    public function buildParameterListExpr(array $parameters): Array_;
}
