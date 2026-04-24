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
use Sindri\Ast\Result\ListenerProviderResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ListenerProviderResultTest extends TestCase
{
    public function testDefaultConstructorHasEmptyArrays(): void
    {
        $result = new ListenerProviderResult();

        self::assertSame([], $result->listenerClasses);
        self::assertSame([], $result->listeners);
    }

    public function testMergeDeduplicatesListenerClasses(): void
    {
        $a = new ListenerProviderResult(listenerClasses: ['ClassA', 'ClassB']);
        $b = new ListenerProviderResult(listenerClasses: ['ClassB', 'ClassC']);

        $merged = $a->merge($b);

        self::assertSame(['ClassA', 'ClassB', 'ClassC'], $merged->listenerClasses);
    }

    public function testMergeCombinesListeners(): void
    {
        $expr1 = new String_('listener1');
        $expr2 = new String_('listener2');

        $a = new ListenerProviderResult(listeners: [$expr1]);
        $b = new ListenerProviderResult(listeners: [$expr2]);

        $merged = $a->merge($b);

        self::assertCount(2, $merged->listeners);
    }
}