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

class TestHttpControllerClass
{
    #[Route(path: '/test', name: 'test.route')]
    public function staticAction(): void
    {
    }

    #[DynamicRoute(path: '/test/{id}', name: 'test.dynamic')]
    #[Parameter(name: 'id', regex: '[0-9]+')]
    public function dynamicAction(): void
    {
    }
}