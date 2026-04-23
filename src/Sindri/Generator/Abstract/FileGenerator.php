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

namespace Sindri\Generator\Abstract;

use Override;
use Sindri\Generator\Contract\FileGeneratorContract;
use Sindri\Generator\Enum\GenerateStatus;
use Throwable;

abstract class FileGenerator implements FileGeneratorContract
{
    protected string $filePath;

    /**
     * @param non-empty-string $directory The directory
     * @param non-empty-string $className The class name
     */
    public function __construct(
        protected string $directory,
        protected string $className,
    ) {
        $this->filePath = rtrim($directory, '/') . "/$className.php";
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function generateFile(): GenerateStatus
    {
        try {
            $data     = $this->generateFileContents();
            $existing = $this->fileGetContents();

            if ($existing === $data) {
                return GenerateStatus::SKIPPED;
            }

            $results = $this->filePutContents(data: $data);

            if ($results !== false) {
                return GenerateStatus::SUCCESS;
            }
        } catch (Throwable) {
            // Fallthrough
        }

        return GenerateStatus::FAILURE;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    abstract public function generateFileContents(): string;

    /**
     * Wrapper for the file_get_contents function.
     */
    protected function fileGetContents(): string|false
    {
        if (! is_file(filename: $this->filePath)) {
            return false;
        }

        return file_get_contents(filename: $this->filePath);
    }

    /**
     * Wrapper for the file_put_contents function.
     */
    protected function filePutContents(string $data): int|false
    {
        return file_put_contents(filename: $this->filePath, data: $data);
    }
}
