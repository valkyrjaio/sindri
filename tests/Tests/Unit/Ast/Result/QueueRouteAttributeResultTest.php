<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Unit\Ast\Result;

use PhpParser\Node\Scalar\String_;
use Sindri\Ast\Data\Result\QueueRouteAttributeResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class QueueRouteAttributeResultTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertSame([], new QueueRouteAttributeResult()->routes);
    }

    public function testConstructor(): void
    {
        $expr = new String_('route-expr');

        self::assertSame(['SendWelcomeEmail' => $expr], new QueueRouteAttributeResult(routes: ['SendWelcomeEmail' => $expr])->routes);
    }
}
