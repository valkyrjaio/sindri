<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Generator\Queue\Contract;

use PhpParser\Node\Expr;
use Sindri\Generator\Enum\GenerateStatus;

interface QueueDataFileGeneratorContract
{
    /**
     * Generate the queue routing data file.
     *
     * @param non-empty-string    $directory
     * @param non-empty-string    $className
     * @param non-empty-string    $namespace
     * @param array<string, Expr> $routes
     */
    public function generateFile(
        string $directory,
        string $className,
        string $namespace,
        array $routes,
    ): GenerateStatus;

    /**
     * Generate the data class contents for inline use.
     *
     * @param array<string, Expr> $routes
     *
     * @return non-empty-string
     */
    public function generateClassContents(array $routes): string;
}
