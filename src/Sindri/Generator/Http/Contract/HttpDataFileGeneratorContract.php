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

namespace Sindri\Generator\Http\Contract;

use PhpParser\Node\Expr;
use Sindri\Ast\Data\HttpRouteData;
use Sindri\Generator\Enum\GenerateStatus;

interface HttpDataFileGeneratorContract
{
    /**
     * Generate the HTTP routing data file.
     *
     * @param non-empty-string             $directory
     * @param non-empty-string             $className
     * @param non-empty-string             $namespace
     * @param array<string, Expr>          $routes
     * @param array<string, HttpRouteData> $routeData
     */
    public function generateFile(
        string $directory,
        string $className,
        string $namespace,
        array $routes,
        array $routeData,
    ): GenerateStatus;

    /**
     * Generate the data class contents for inline use.
     *
     * @param array<string, Expr>          $routes
     * @param array<string, HttpRouteData> $routeData
     *
     * @return non-empty-string
     */
    public function generateClassContents(
        array $routes,
        array $routeData,
    ): string;
}
