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
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Data\CliArgumentParameterData;
use Sindri\Ast\Data\CliOptionParameterData;
use Sindri\Ast\Data\CliRouteData;

/**
 * Reads and builds AST expressions for CLI route argument and option parameters.
 */
interface CliRouteParameterReaderContract
{
    /**
     * Build the arguments/options named-arg list for a CliRouteData.
     *
     * @return Arg[]
     */
    public function buildParameterArgs(CliRouteData $data): array;

    /**
     * Collect all #[ArgumentParameter] attributes from the method.
     *
     * @param array<string, string> $useMap
     *
     * @return CliArgumentParameterData[]
     */
    public function updateArguments(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array;

    /**
     * Collect all #[OptionParameter] attributes from the method.
     *
     * @param array<string, string> $useMap
     *
     * @return CliOptionParameterData[]
     */
    public function updateOptions(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array;
}