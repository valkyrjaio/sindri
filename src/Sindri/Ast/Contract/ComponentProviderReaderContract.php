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

use Sindri\Ast\Result\ComponentProviderResult;

interface ComponentProviderReaderContract
{
    /**
     * Read the provider lists declared in the given ComponentProviderContract source file.
     *
     * Returns the class names found in all five methods:
     *   - getComponentProviders → componentProviders
     *   - getContainerProviders → serviceProviders
     *   - getEventProviders     → listenerProviders
     *   - getCliProviders       → cliRouteProviders
     *   - getHttpProviders      → httpRouteProviders
     *
     * The caller is responsible for resolving the returned class names to file paths
     * (e.g. via PSR-4 derivation from the source root) and walking the tree.
     *
     * @param non-empty-string $filePath Absolute path to the PHP source file
     */
    public function readFile(string $filePath): ComponentProviderResult;
}