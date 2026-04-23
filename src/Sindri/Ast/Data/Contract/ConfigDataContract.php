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
 * Contract for a portable application config intermediate representation.
 */
interface ConfigDataContract
{
    public string $namespace {
        get;
    }

    public string $dir {
        get;
    }

    public string $dataPath {
        get;
    }

    public string $dataNamespace {
        get;
    }

    /** @var class-string[] */
    public array $providers {
        get;
    }
}