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

namespace Sindri\Throwable\Exception\Abstract;

use Sindri\Throwable\Contract\SindriThrowable;
use Valkyrja\Throwable\Exception\Abstract\ValkyrjaRuntimeException;

abstract class SindriRuntimeException extends ValkyrjaRuntimeException implements SindriThrowable
{
}
