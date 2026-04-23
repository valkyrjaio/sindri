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

namespace Sindri\Generator\Container\Contract;

use Sindri\Generator\Contract\FileGeneratorContract;

interface DataFileGeneratorContract extends FileGeneratorContract
{
    /**
     * Generate the data class contents.
     *
     * @return non-empty-string
     */
    public function generateClassContents(): string;
}
