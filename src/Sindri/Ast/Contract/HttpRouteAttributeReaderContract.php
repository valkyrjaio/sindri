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

use Sindri\Ast\Result\HttpRouteAttributeResult;

/**
 * Reads #[Route] / #[DynamicRoute] and related HTTP routing attributes from a controller class file.
 */
interface HttpRouteAttributeReaderContract
{
    /**
     * Scan an HTTP controller class source file and return all extracted route data objects.
     */
    public function readFile(string $filePath): HttpRouteAttributeResult;
}
