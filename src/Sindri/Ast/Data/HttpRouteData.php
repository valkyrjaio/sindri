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

namespace Sindri\Ast\Data;

use Sindri\Ast\Data\Contract\HttpRouteDataContract;

/**
 * Portable intermediate representation of a single HTTP route extracted from
 * #[Route] / #[DynamicRoute] and related sub-attributes on a controller method.
 *
 * Mirrors the shape of Valkyrja\Http\Routing\Data\Route (and DynamicRoute)
 * without requiring the framework data class to be instantiated.
 *
 * When isDynamic is true the parameters and regex fields are populated.
 * Middleware class names are stored as FQN strings.
 * Request method enum values are stored as "FQN::CASE" strings.
 * The requestStruct and responseStruct fields hold FQN class-strings or null.
 */
readonly class HttpRouteData implements HttpRouteDataContract
{
    /**
     * @param string              $path                      Route path
     * @param string              $name                      Route name
     * @param HandlerData|null    $handler                   Resolved handler (from #[RouteHandler])
     * @param string[]            $requestMethods            "FQN::CASE" strings for RequestMethod enum values
     * @param class-string[]      $routeMatchedMiddleware
     * @param class-string[]      $routeDispatchedMiddleware
     * @param class-string[]      $throwableCaughtMiddleware
     * @param class-string[]      $sendingResponseMiddleware
     * @param class-string[]      $terminatedMiddleware
     * @param class-string|null   $requestStruct             FQN of the request struct class, or null
     * @param class-string|null   $responseStruct            FQN of the response struct class, or null
     * @param bool                $isDynamic                 Whether this is a dynamic (parameterized) route
     * @param HttpParameterData[] $parameters                URL parameters (populated when isDynamic is true)
     */
    public function __construct(
        public string $path,
        public string $name,
        public HandlerData|null $handler = null,
        public array $requestMethods = [],
        public array $routeMatchedMiddleware = [],
        public array $routeDispatchedMiddleware = [],
        public array $throwableCaughtMiddleware = [],
        public array $sendingResponseMiddleware = [],
        public array $terminatedMiddleware = [],
        public string|null $requestStruct = null,
        public string|null $responseStruct = null,
        public bool $isDynamic = false,
        public array $parameters = [],
    ) {
    }
}
