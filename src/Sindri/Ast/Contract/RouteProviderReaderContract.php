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

use Sindri\Ast\Result\RouteProviderResult;

interface RouteProviderReaderContract
{
    /**
     * Read the routes declared by parsing the given CliRouteProviderContract or
     * HttpRouteProviderContract source file.
     *
     * Extracts:
     *   - controllerClasses from getControllerClasses() — attributed classes for later
     *     attribute scanning to produce full route data objects.
     *   - routes from getRoutes() — manually-defined data objects whose exact
     *     shapes are preserved as-is.
     *
     * This contract is shared by both CLI and HTTP route providers since their
     * structures are identical.
     *
     * @param non-empty-string $filePath Absolute path to the PHP source file
     */
    public function readFile(string $filePath): RouteProviderResult;
}
