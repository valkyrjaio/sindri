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

namespace Sindri\Tests\Unit\Ast\Result;

use PhpParser\Node\Scalar\String_;
use Sindri\Ast\Result\RouteProviderResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class RouteProviderResultTest extends TestCase
{
    public function testDefaultConstructorHasEmptyArrays(): void
    {
        $result = new RouteProviderResult();

        self::assertSame([], $result->controllerClasses);
        self::assertSame([], $result->routes);
    }

    public function testMergeDeduplicatesControllerClasses(): void
    {
        $a = new RouteProviderResult(controllerClasses: ['ControllerA', 'ControllerB']);
        $b = new RouteProviderResult(controllerClasses: ['ControllerB', 'ControllerC']);

        $merged = $a->merge($b);

        self::assertSame(['ControllerA', 'ControllerB', 'ControllerC'], $merged->controllerClasses);
    }

    public function testMergeCombinesRoutes(): void
    {
        $expr1 = new String_('route1');
        $expr2 = new String_('route2');

        $a = new RouteProviderResult(routes: [$expr1]);
        $b = new RouteProviderResult(routes: [$expr2]);

        $merged = $a->merge($b);

        self::assertCount(2, $merged->routes);
    }
}
