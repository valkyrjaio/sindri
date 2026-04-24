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

use Sindri\Tests\Classes\Event\TestEventClass;
use Valkyrja\Event\Attribute\Listener;

#[Listener(eventId: TestEventClass::class, name: 'no-ns-class-listener')]
class TestListenerNoNsClass
{
    #[Listener(eventId: TestEventClass::class, name: 'no-ns-method-listener')]
    public function handle(): void
    {
    }
}
