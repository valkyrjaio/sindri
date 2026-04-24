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

namespace Sindri\Tests\Classes\Cli\Controller;

use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;

class TestBadCliControllerClass
{
    // Route with Middleware(true) — a non-string middleware value that triggers the continue branch
    #[Route(name: 'bad:non-string-middleware', description: 'Route with non-string middleware arg')]
    #[Middleware(true)]
    public function nonStringMiddlewareAction(): void
    {
    }

    // Route with an empty name — triggers return null in buildRouteData
    #[Route(name: '', description: 'Route with empty name')]
    public function emptyNameAction(): void
    {
    }

    // Route with a valid name but an argument with a non-string description — triggers return null in buildArgumentData
    #[Route(name: 'bad:arg-non-string-desc', description: 'Route with bad argument')]
    #[ArgumentParameter(name: 'bad', description: true)]
    public function badArgumentAction(): void
    {
    }

    // Route with a valid name but an option with a non-string description — triggers return null in buildOptionData
    #[Route(name: 'bad:opt-non-string-desc', description: 'Route with bad option')]
    #[OptionParameter(name: 'bad', description: true)]
    public function badOptionAction(): void
    {
    }
}