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
 * Contract for a portable callable representation stored as class + method pair.
 */
interface HandlerDataContract
{
    /** @var class-string */
    public string $class {
        get;
    }

    public string $method {
        get;
    }
}
