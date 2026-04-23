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

namespace Sindri\Ast\Data;

use Sindri\Ast\Data\Contract\ListenerDataContract;

/**
 * Portable intermediate representation of a single listener extracted from
 * #[Listener] and #[ListenerHandler] attributes.
 *
 * Mirrors the shape of Valkyrja\Event\Data\Listener without requiring the
 * framework data class to be instantiated.
 */
readonly class ListenerData implements ListenerDataContract
{
    /**
     * @param class-string  $eventId The event class FQN this listener handles
     * @param string        $name    Unique listener name
     * @param HandlerData|null $handler The callable handler, null if not resolved from attributes
     */
    public function __construct(
        public string $eventId,
        public string $name,
        public HandlerData|null $handler = null,
    ) {
    }
}