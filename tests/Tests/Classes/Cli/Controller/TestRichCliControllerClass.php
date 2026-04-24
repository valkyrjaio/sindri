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

use Sindri\Tests\Classes\Cli\Middleware\TestCliMiddlewareClass;
use Valkyrja\Cli\Routing\Attribute\ArgumentParameter;
use Valkyrja\Cli\Routing\Attribute\OptionParameter;
use Valkyrja\Cli\Routing\Attribute\Route;
use Valkyrja\Cli\Routing\Attribute\Route\Middleware;
use Valkyrja\Cli\Routing\Attribute\Route\Name as RouteName;
use Valkyrja\Cli\Routing\Attribute\Route\RouteHandler;
use Valkyrja\Cli\Routing\Enum\ArgumentMode;

class TestRichCliControllerClass
{
    // Route with Middleware attribute to cover updateMiddleware loop body (is_a → true)
    #[Route(name: 'rich:middleware', description: 'Route with middleware')]
    #[Middleware(TestCliMiddlewareClass::class)]
    public function middlewareAction(): void
    {
    }

    // Route with Name override (covers updateName body)
    #[Route(name: 'original:name', description: 'Route with name override')]
    #[RouteName('overridden:name')]
    public function namedAction(): void
    {
    }

    // Route with argument that has a non-null cast (covers cast !== null branch in buildArgumentExpr)
    #[Route(name: 'rich:cast-arg', description: 'Route with cast argument')]
    #[ArgumentParameter(name: 'count', description: 'A count argument', cast: ArgumentMode::REQUIRED)]
    public function castArgAction(): void
    {
    }

    // Route with option that has validValues (covers validValues branch in buildOptionExpr)
    #[Route(name: 'rich:valid-values', description: 'Route with valid-values option')]
    #[OptionParameter(name: 'format', description: 'Output format', validValues: ['json', 'xml'])]
    public function validValuesAction(): void
    {
    }

    // Route with option that has a non-null cast (covers cast !== null branch in buildOptionExpr)
    #[Route(name: 'rich:cast-opt', description: 'Route with cast option')]
    #[OptionParameter(name: 'mode', description: 'Mode option', cast: ArgumentMode::OPTIONAL)]
    public function castOptAction(): void
    {
    }

    // Route with inline middleware arrays in the #[Route] attribute itself (covers buildRouteData lines 128/132/136/140)
    #[Route(
        name: 'rich:inline-middleware',
        description: 'Route with inline middleware arrays',
        routeMatchedMiddleware: [TestCliMiddlewareClass::class],
        routeDispatchedMiddleware: [TestCliMiddlewareClass::class],
        throwableCaughtMiddleware: [TestCliMiddlewareClass::class],
        exitedMiddleware: [TestCliMiddlewareClass::class],
    )]
    public function inlineMiddlewareAction(): void
    {
    }

    // Route with #[RouteHandler] attribute (covers updateHandler lines 204–207)
    #[Route(name: 'rich:custom-handler', description: 'Route with custom handler')]
    #[RouteHandler([TestCliControllerClass::class, 'testAction'])]
    public function customHandlerAction(): void
    {
    }
}
