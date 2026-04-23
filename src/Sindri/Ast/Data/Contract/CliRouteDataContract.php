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
 * Contract for a portable CLI route intermediate representation.
 */
interface CliRouteDataContract
{
    public string $name {
        get;
    }

    public string $description {
        get;
    }

    public HandlerDataContract|null $handler {
        get;
    }

    public HandlerDataContract|null $helpText {
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
    public array $exitedMiddleware {
        get;
    }

    /** @var CliArgumentParameterDataContract[] */
    public array $arguments {
        get;
    }

    /** @var CliOptionParameterDataContract[] */
    public array $options {
        get;
    }
}