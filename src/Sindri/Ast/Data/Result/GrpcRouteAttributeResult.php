<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Ast\Data\Result;

use PhpParser\Node\Expr;

readonly class GrpcRouteAttributeResult
{
    /**
     * @param array<string, Expr> $routes Fully-qualified method → AST expression (keyed for direct
     *                                    use in the generated service map)
     */
    public function __construct(
        public array $routes = [],
    ) {
    }
}
