<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Ast\Data;

use Sindri\Ast\Data\Contract\GrpcRouteDataContract;

readonly class GrpcRouteData implements GrpcRouteDataContract
{
    /**
     * @param string            $method                    The fully-qualified method, `/package.Service/Method`
     * @param HandlerData|null  $handler                   Resolved handler (the attributed method, or a #[MethodHandler] override)
     * @param class-string|null $requestType               The generated protobuf request message type
     * @param class-string|null $responseType              The generated protobuf response message type
     * @param class-string[]    $routeMatchedMiddleware
     * @param class-string[]    $routeDispatchedMiddleware
     * @param class-string[]    $throwableCaughtMiddleware
     * @param class-string[]    $sendingResponseMiddleware
     * @param class-string[]    $responseSentMiddleware
     */
    public function __construct(
        public string $method,
        public HandlerData|null $handler = null,
        public string|null $requestType = null,
        public string|null $responseType = null,
        public bool $clientStreaming = false,
        public bool $serverStreaming = false,
        public array $routeMatchedMiddleware = [],
        public array $routeDispatchedMiddleware = [],
        public array $throwableCaughtMiddleware = [],
        public array $sendingResponseMiddleware = [],
        public array $responseSentMiddleware = [],
    ) {
    }
}
