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

namespace Sindri\Generator\Contract;

use Sindri\Generator\Enum\GenerateStatus;

interface FileGeneratorContract
{
    /**
     * Generate a file.
     */
    public function generateFile(): GenerateStatus;

    /**
     * Generate the file contents.
     *
     * @return non-empty-string
     */
    public function generateFileContents(): string;
}
