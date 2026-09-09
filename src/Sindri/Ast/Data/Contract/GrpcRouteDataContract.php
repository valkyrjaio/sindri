<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Ast\Data\Contract;

interface GrpcRouteDataContract
{
    /** The fully-qualified method, `/package.Service/Method` */
    public string $method {
        get;
    }

    public HandlerDataContract|null $handler {
        get;
    }

    /** @var class-string|null */
    public string|null $requestType {
        get;
    }

    /** @var class-string|null */
    public string|null $responseType {
        get;
    }

    public bool $clientStreaming {
        get;
    }

    public bool $serverStreaming {
        get;
    }

    /** @var class-string[] */
    public array $routeMatchedMiddleware {
        get;
    }

    /** @var class-string[] */
    public array $routeDispatchedMiddleware {
        get;
    }

    /** @var class-string[] */
    public array $throwableCaughtMiddleware {
        get;
    }

    /** @var class-string[] */
    public array $sendingResponseMiddleware {
        get;
    }

    /** @var class-string[] */
    public array $responseSentMiddleware {
        get;
    }
}
