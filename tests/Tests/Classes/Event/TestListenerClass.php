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

namespace Sindri\Tests\Classes\Event;

use Valkyrja\Event\Attribute\Listener;

#[Listener(eventId: TestEventClass::class, name: 'test-class-listener')]
class TestListenerClass
{
    #[Listener(eventId: TestEventClass::class, name: 'test-method-listener')]
    public function handle(): void
    {
    }
}