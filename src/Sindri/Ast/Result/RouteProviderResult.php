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

namespace Sindri\Ast\Result;

/**
 * Routes extracted from a single CliRouteProviderContract or HttpRouteProviderContract implementation.
 *
 * `controllerClasses` — classes listed by getControllerClasses() that carry #[Route]
 *                        attributes; a subsequent AttributeRouteReader will scan each
 *                        class and produce the full route data objects.
 *
 * `routes`             — Route data objects returned directly by getRoutes();
 *                        the exact user-defined shapes are preserved as-is.
 *                        (Populated once AST parsing of getRoutes() new-expressions
 *                        is implemented.)
 *
 * This result is shared by both CLI and HTTP route providers since their contracts
 * are structurally identical (getControllerClasses / getRoutes).
 */
readonly class RouteProviderResult
{
    /**
     * @param class-string[] $controllerClasses
     * @param object[]       $routes            Route data objects (CliRoutingData\RouteContract or HttpRoutingData\RouteContract)
     */
    public function __construct(
        public array $controllerClasses = [],
        public array $routes = [],
    ) {
    }

    /**
     * Merge another result into this one, deduplicating the controller class list.
     */
    public function merge(self $other): self
    {
        return new self(
            controllerClasses: array_values(array_unique([...$this->controllerClasses, ...$other->controllerClasses])),
            routes: [...$this->routes, ...$other->routes],
        );
    }
}