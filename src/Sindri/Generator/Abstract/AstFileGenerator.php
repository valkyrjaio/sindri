<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Generator\Abstract;

use Sindri\Generator\Enum\GenerateStatus;
use Throwable;

abstract class AstFileGenerator
{
    protected function writeFile(string $directory, string $className, string $data): GenerateStatus
    {
        $filePath = rtrim($directory, '/') . "/$className.php";

        try {
            $existing = is_file($filePath) ? file_get_contents($filePath) : false;

            if ($existing === $data) {
                return GenerateStatus::SKIPPED;
            }

            $result = file_put_contents($filePath, $data);

            if ($result !== false) {
                return GenerateStatus::SUCCESS;
            }
        } catch (Throwable) {
            // Fallthrough
        }

        return GenerateStatus::FAILURE;
    }
}