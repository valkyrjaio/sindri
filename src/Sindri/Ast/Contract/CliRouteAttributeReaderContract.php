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

use Sindri\Ast\Result\CliRouteAttributeResult;

/**
 * Reads #[Route] and related CLI routing attributes from a controller class file.
 */
interface CliRouteAttributeReaderContract
{
    /**
     * Scan a CLI controller class source file and return all extracted route data objects.
     */
    public function readFile(string $filePath): CliRouteAttributeResult;
}