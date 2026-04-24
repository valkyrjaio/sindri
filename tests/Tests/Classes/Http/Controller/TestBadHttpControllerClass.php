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

use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Attribute\Parameter;
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

    // Route with empty name — causes buildRouteData to return null (line 179)
    #[Route(path: '/bad/empty-name', name: '')]
    public function emptyNameAction(): void
    {
    }

    // DynamicRoute with non-New_ in inline parameters array — buildParameterFromExpr returns null (line 574)
    #[DynamicRoute(path: '/bad/{id}', name: 'bad.non-new', parameters: ['not-a-parameter'])]
    public function nonNewParamAction(): void
    {
    }

    // DynamicRoute with Parameter having empty name — buildParameterData returns null (line 598)
    #[DynamicRoute(path: '/bad/empty/{id}', name: 'bad.empty-param')]
    #[Parameter(name: '', regex: '[0-9]+')]
    public function emptyParamNameAction(): void
    {
    }
}