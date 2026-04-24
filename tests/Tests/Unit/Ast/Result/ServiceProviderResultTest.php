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

use Sindri\Ast\Result\ServiceProviderResult;
use Sindri\Tests\Unit\Abstract\TestCase;

final class ServiceProviderResultTest extends TestCase
{
    public function testDefaultConstructorHasEmptyArrays(): void
    {
        $result = new ServiceProviderResult();

        self::assertSame([], $result->serviceClasses);
        self::assertSame([], $result->publishers);
    }

    public function testMergeDeduplicatesServiceClasses(): void
    {
        $a = new ServiceProviderResult(serviceClasses: ['ClassA', 'ClassB']);
        $b = new ServiceProviderResult(serviceClasses: ['ClassB', 'ClassC']);

        $merged = $a->merge($b);

        self::assertSame(['ClassA', 'ClassB', 'ClassC'], $merged->serviceClasses);
    }

    public function testMergeCombinesPublishers(): void
    {
        $a = new ServiceProviderResult(publishers: ['ClassA' => ['ProviderA', 'publishA']]);
        $b = new ServiceProviderResult(publishers: ['ClassB' => ['ProviderB', 'publishB']]);

        $merged = $a->merge($b);

        self::assertSame(['ProviderA', 'publishA'], $merged->publishers['ClassA']);
        self::assertSame(['ProviderB', 'publishB'], $merged->publishers['ClassB']);
    }
}
