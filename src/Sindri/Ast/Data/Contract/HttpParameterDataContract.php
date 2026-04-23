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
 * Contract for a portable HTTP route parameter intermediate representation.
 */
interface HttpParameterDataContract
{
    public string $name {
        get;
    }

    public string $regex {
        get;
    }

    /** Stored as "FQN::CASE" or null */
    public string|null $cast {
        get;
    }

    public bool $isOptional {
        get;
    }

    public bool $shouldCapture {
        get;
    }
}
