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
 * Contract for a portable listener intermediate representation.
 */
interface ListenerDataContract
{
    /** @var class-string */
    public string $eventId {
        get;
    }

    public string $name {
        get;
    }

    public HandlerDataContract|null $handler {
        get;
    }
}