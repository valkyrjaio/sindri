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

namespace Sindri\Ast\Result;

use Valkyrja\Event\Data\Contract\ListenerContract;

/**
 * Listeners extracted from a single ListenerProviderContract implementation.
 *
 * `listenerClasses` — classes listed by getListenerClasses() that carry #[Listener]
 *                     attributes; a subsequent AttributeListenerReader will scan each
 *                     class and produce the full ListenerContract objects.
 *
 * `listeners`       — ListenerContract objects returned directly by getListeners();
 *                     the exact user-defined shapes are preserved as-is.
 *                     (Populated once AST parsing of getListeners() new-expressions
 *                     is implemented.)
 */
readonly class ListenerProviderResult
{
    /**
     * @param class-string[]    $listenerClasses
     * @param ListenerContract[] $listeners
     */
    public function __construct(
        public array $listenerClasses = [],
        public array $listeners = [],
    ) {
    }

    /**
     * Merge another result into this one, deduplicating the attributed class list.
     */
    public function merge(self $other): self
    {
        return new self(
            listenerClasses: array_values(array_unique([...$this->listenerClasses, ...$other->listenerClasses])),
            listeners: [...$this->listeners, ...$other->listeners],
        );
    }
}