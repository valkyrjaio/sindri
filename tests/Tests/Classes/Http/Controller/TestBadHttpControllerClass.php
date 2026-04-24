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

namespace Sindri\Tests\Classes\Http\Controller;

use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;

class TestBadHttpControllerClass
{
    // Route with Middleware(true) — a non-string middleware value that triggers the continue branch
    #[Route(path: '/bad/middleware', name: 'bad.non-string-middleware')]
    #[Middleware(true)]
    public function nonStringMiddlewareAction(): void
    {
    }
}