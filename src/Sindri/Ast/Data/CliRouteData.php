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

use Sindri\Ast\Data\Contract\CliRouteDataContract;

/**
 * Portable intermediate representation of a single CLI route extracted from
 * #[Route] and related sub-attributes on a controller method.
 *
 * Mirrors the shape of Valkyrja\Cli\Routing\Data\Route without requiring the
 * framework data class to be instantiated.
 *
 * Middleware class names are stored as FQN strings.
 */
readonly class CliRouteData implements CliRouteDataContract
{
    /**
     * @param string                     $name                      Route name
     * @param string                     $description               Route description
     * @param HandlerData|null           $handler                   Resolved handler (from #[RouteHandler])
     * @param HandlerData|null           $helpText                  Help text callable (from route attribute helpText arg)
     * @param class-string[]             $routeMatchedMiddleware
     * @param class-string[]             $routeDispatchedMiddleware
     * @param class-string[]             $throwableCaughtMiddleware
     * @param class-string[]             $exitedMiddleware
     * @param CliArgumentParameterData[] $arguments
     * @param CliOptionParameterData[]   $options
     */
    public function __construct(
        public string $name,
        public string $description,
        public HandlerData|null $handler = null,
        public HandlerData|null $helpText = null,
        public array $routeMatchedMiddleware = [],
        public array $routeDispatchedMiddleware = [],
        public array $throwableCaughtMiddleware = [],
        public array $exitedMiddleware = [],
        public array $arguments = [],
        public array $options = [],
    ) {
    }
}