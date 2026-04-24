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

use Sindri\Tests\Classes\Http\Middleware\TestHttpMiddlewareClass;
use Valkyrja\Http\Message\Enum\RequestMethod;
use Valkyrja\Http\Routing\Attribute\DynamicRoute;
use Valkyrja\Http\Routing\Attribute\Parameter;
use Valkyrja\Http\Routing\Attribute\Route;
use Valkyrja\Http\Routing\Attribute\Route\Middleware;
use Valkyrja\Http\Routing\Attribute\Route\Name;
use Valkyrja\Http\Routing\Attribute\Route\Path;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod as RouteRequestMethod;
use Valkyrja\Http\Routing\Attribute\Route\RequestStruct;
use Valkyrja\Http\Routing\Attribute\Route\ResponseStruct;
use Valkyrja\Http\Routing\Attribute\Route\RouteHandler;

#[Path('/api')]
#[Name('api')]
final class TestRichHttpControllerClass
{
    // Route with class-level path/name prefix + method-level Path and Name suffix
    #[Route(path: '/users', name: 'users.list')]
    #[Path('/list')]
    #[Name('paginated')]
    public function listUsers(): void
    {
    }

    // Route with all 5 middleware types classified via Middleware attribute
    #[Route(path: '/users/create', name: 'users.create')]
    #[Middleware(TestHttpMiddlewareClass::class)]
    public function createUser(): void
    {
    }

    // Route with RequestStruct and ResponseStruct (class-string arg for AST extraction)
    #[Route(path: '/users/update', name: 'users.update')]
    #[RequestStruct(TestHttpMiddlewareClass::class)]
    #[ResponseStruct(TestHttpMiddlewareClass::class)]
    public function updateUser(): void
    {
    }

    // Route with RouteRequestMethod attribute (covers updateRequestMethods loop body)
    #[Route(path: '/orders', name: 'orders.post')]
    #[RouteRequestMethod(RequestMethod::POST)]
    public function createOrder(): void
    {
    }

    // Dynamic route with inline parameters array (covers buildParameterFromExpr)
    #[DynamicRoute(path: '/items/{id}', name: 'items.show', parameters: [new Parameter(name: 'id', regex: '[0-9]+')])]
    public function showItem(): void
    {
    }

    // Dynamic route with PHP method-parameter-level #[Parameter]
    #[DynamicRoute(path: '/products/{slug}', name: 'products.show', parameters: [])]
    public function showProduct(#[Parameter(name: 'slug', regex: '[a-z-]+')] string $slug): void
    {
    }

    // Route with all 5 inline middleware arrays directly in #[Route] (covers lines 204-220)
    #[Route(
        path: '/inline/middleware',
        name: 'inline.middleware',
        routeMatchedMiddleware: [TestHttpMiddlewareClass::class],
        routeDispatchedMiddleware: [TestHttpMiddlewareClass::class],
        throwableCaughtMiddleware: [TestHttpMiddlewareClass::class],
        sendingResponseMiddleware: [TestHttpMiddlewareClass::class],
        terminatedMiddleware: [TestHttpMiddlewareClass::class],
    )]
    public function inlineMiddlewareAction(): void
    {
    }

    // Route with #[RouteHandler] attribute (covers updateHandler lines 330-333)
    #[Route(path: '/custom/handler', name: 'custom.handler')]
    #[RouteHandler([TestHttpControllerClass::class, 'staticAction'])]
    public function customHandlerAction(): void
    {
    }

    // Route with inline requestMethods array in #[Route] itself (covers line 357)
    #[Route(path: '/inline/methods', name: 'inline.methods', requestMethods: [RequestMethod::POST])]
    public function inlineRequestMethodsAction(): void
    {
    }

    // Dynamic route with Parameter using enum-case regex (covers buildParameterExpr line 693)
    #[DynamicRoute(path: '/enum/{method}', name: 'enum.method')]
    #[Parameter(name: 'method', regex: RequestMethod::GET)]
    public function enumRegexParamAction(): void
    {
    }

    // Dynamic route with Parameter having a non-null cast (covers buildParameterExpr line 702)
    #[DynamicRoute(path: '/cast/{id}', name: 'cast.param')]
    #[Parameter(name: 'id', regex: '[0-9]+', cast: TestHttpMiddlewareClass::class)]
    public function castParamAction(): void
    {
    }
}
