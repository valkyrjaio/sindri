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

use Sindri\Ast\Data\Contract\QueueRouteDataContract;

readonly class QueueRouteData implements QueueRouteDataContract
{
    /**
     * @param string           $name                      Job name — the router map key
     * @param string           $description               Route description
     * @param HandlerData|null $handler                   Resolved handler (from #[RouteHandler])
     * @param class-string[]   $routeMatchedMiddleware
     * @param class-string[]   $routeDispatchedMiddleware
     * @param class-string[]   $throwableCaughtMiddleware
     * @param class-string[]   $settlingResultMiddleware
     * @param class-string[]   $resultSettledMiddleware
     */
    public function __construct(
        public string $name,
        public string $description,
        public HandlerData|null $handler = null,
        public array $routeMatchedMiddleware = [],
        public array $routeDispatchedMiddleware = [],
        public array $throwableCaughtMiddleware = [],
        public array $settlingResultMiddleware = [],
        public array $resultSettledMiddleware = [],
    ) {
    }
}
