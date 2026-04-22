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

use Sindri\Ast\Result\ServiceProviderResult;

interface ServiceProviderReaderContract
{
    /**
     * Read the services published by parsing the given ServiceProviderContract source file.
     *
     * Extracts the keys of the `publishers()` return array as fully-qualified
     * service class names.
     *
     * @param non-empty-string $filePath Absolute path to the PHP source file
     */
    public function readFile(string $filePath): ServiceProviderResult;
}