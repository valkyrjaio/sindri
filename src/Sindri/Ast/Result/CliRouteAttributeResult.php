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

namespace Sindri\Ast\Result;

use PhpParser\Node\Expr;

/**
 * Result of scanning a CLI controller class file for #[Route] and related attributes.
 *
 * Each element of $routes is a PHP-Parser Expr node (typically Expr\New_)
 * ready to be embedded verbatim in the data cache file by the generator.
 */
readonly class CliRouteAttributeResult
{
    /**
     * @param array<string, Expr> $routes Route name → AST expression (keyed for direct use in generated arrays)
     */
    public function __construct(
        public array $routes = [],
    ) {
    }
}
