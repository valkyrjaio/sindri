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

namespace Sindri\Generator\Event\Contract;

use PhpParser\Node\Expr;
use Sindri\Generator\Enum\GenerateStatus;

interface EventDataFileGeneratorContract
{
    /**
     * Generate the event listener data file.
     *
     * @param non-empty-string    $directory
     * @param non-empty-string    $className
     * @param non-empty-string    $namespace
     * @param array<string, Expr> $listeners
     */
    public function generateFile(
        string $directory,
        string $className,
        string $namespace,
        array $listeners,
    ): GenerateStatus;

    /**
     * Generate the data class contents for inline use.
     *
     * @param array<string, Expr> $listeners
     *
     * @return non-empty-string
     */
    public function generateClassContents(array $listeners): string;
}
