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

namespace Sindri\Ast\Data\Contract;

/**
 * Contract for a portable HTTP route intermediate representation.
 */
interface HttpRouteDataContract
{
    public string $path {
        get;
    }

    public string $name {
        get;
    }

    public HandlerDataContract|null $handler {
        get;
    }

    /** @var string[] "FQN::CASE" strings for RequestMethod enum values */
    public array $requestMethods {
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
    public array $terminatedMiddleware {
        get;
    }

    /** @var class-string|null */
    public string|null $requestStruct {
        get;
    }

    /** @var class-string|null */
    public string|null $responseStruct {
        get;
    }

    public bool $isDynamic {
        get;
    }

    /** @var HttpParameterDataContract[] */
    public array $parameters {
        get;
    }
}