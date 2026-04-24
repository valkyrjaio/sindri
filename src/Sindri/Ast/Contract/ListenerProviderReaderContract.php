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

use Sindri\Ast\Result\ListenerProviderResult;

interface ListenerProviderReaderContract
{
    /**
     * Read the listeners declared by parsing the given ListenerProviderContract source file.
     *
     * Extracts:
     *   - listenerClasses from getListenerClasses() — attributed classes for later
     *     attribute scanning to produce full ListenerContract objects.
     *   - listeners from getListeners() — manually-defined data objects whose exact
     *     shapes are preserved as-is.
     *
     * @param non-empty-string $filePath Absolute path to the PHP source file
     */
    public function readFile(string $filePath): ListenerProviderResult;
}
