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

namespace Sindri\Tests\Unit\Ast\Data;

use Sindri\Ast\Data\HandlerData;
use Sindri\Tests\Unit\Abstract\TestCase;

final class HandlerDataTest extends TestCase
{
    public function testConstructorStoresClass(): void
    {
        $data = new HandlerData(class: 'SomeClass', method: 'someMethod');

        self::assertSame('SomeClass', $data->class);
    }

    public function testConstructorStoresMethod(): void
    {
        $data = new HandlerData(class: 'SomeClass', method: 'someMethod');

        self::assertSame('someMethod', $data->method);
    }
}
