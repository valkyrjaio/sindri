<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Ast\Contract;

use Sindri\Ast\Data\Result\GrpcRouteAttributeResult;

interface GrpcRouteAttributeReaderContract
{
    /**
     * Scan a gRPC service controller class source file and return all extracted route data objects.
     */
    public function readFile(string $filePath): GrpcRouteAttributeResult;
}
