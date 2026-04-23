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
 * Contract for a portable CLI option parameter intermediate representation.
 */
interface CliOptionParameterDataContract
{
    public string $name {
        get;
    }

    public string $description {
        get;
    }

    public string $valueDisplayName {
        get;
    }

    public string|null $cast {
        get;
    }

    public string $defaultValue {
        get;
    }

    /** @var string[] */
    public array $shortNames {
        get;
    }

    /** @var string[] */
    public array $validValues {
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