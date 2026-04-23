<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Constant;

final class CommandName
{
    /** @var non-empty-string */
    public const string CLI_DATA_GENERATE = 'cli:data:generate';
    /** @var non-empty-string */
    public const string HTTP_DATA_GENERATE = 'http:data:generate';
}
