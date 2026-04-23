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
 * Result of scanning a listener class file for #[Listener] / #[ListenerHandler] attributes.
 *
 * Each element of $listeners is a PHP-Parser Expr node (typically Expr\New_)
 * ready to be embedded verbatim in the data cache file by the generator.
 */
readonly class ListenerAttributeResult
{
    /**
     * @param array<string, Expr> $listeners Listener name → AST expression (keyed for direct use in generated arrays)
     */
    public function __construct(
        public array $listeners = [],
    ) {
    }
}