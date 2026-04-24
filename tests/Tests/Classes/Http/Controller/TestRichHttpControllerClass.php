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
use Valkyrja\Http\Routing\Attribute\Route\Name as RouteName;
use Valkyrja\Http\Routing\Attribute\Route\Path as RoutePath;
use Valkyrja\Http\Routing\Attribute\Route\RequestMethod as RouteRequestMethod;
use Valkyrja\Http\Routing\Attribute\Route\RequestStruct;
use Valkyrja\Http\Routing\Attribute\Route\ResponseStruct;

#[RoutePath('/api')]
#[RouteName('api')]
class TestRichHttpControllerClass
{
    // Route with class-level path/name prefix + method-level Path and Name suffix
    #[Route(path: '/users', name: 'users.list')]
    #[RoutePath('/list')]
    #[RouteName('paginated')]
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
}