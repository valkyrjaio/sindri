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
 * Contract for a portable CLI argument parameter intermediate representation.
 */
interface CliArgumentParameterDataContract
{
    public string $name {
        get;
    }

    public string $description {
        get;
    }

    public string|null $cast {
        get;
    }

    /** Stored as "FQN::CASE" */
    public string $mode {
        get;
    }

    /** Stored as "FQN::CASE" */
    public string $valueMode {
        get;
    }
}