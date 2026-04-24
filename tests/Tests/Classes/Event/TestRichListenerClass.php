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
use Valkyrja\Event\Attribute\ListenerHandler;

// Listener with an explicit #[ListenerHandler] attribute on the class level
#[Listener(eventId: TestEventClass::class, name: 'handler-class-listener')]
#[ListenerHandler([TestListenerClass::class, 'handle'])]
class TestRichListenerClass
{
    // Method-level listener with an inline handler array directly in #[Listener]
    // covers the `$handlerRaw instanceof HandlerData ? $handlerRaw : ...` true branch
    #[Listener(eventId: TestEventClass::class, name: 'inline-handler-method-listener', handler: [TestListenerClass::class, 'handle'])]
    public function handle(): void
    {
    }
}