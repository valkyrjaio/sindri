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

use PhpParser\Node\Expr;

/**
 * Listeners extracted from a single ListenerProviderContract implementation.
 *
 * `listenerClasses` — classes listed by getListenerClasses() that carry #[Listener]
 *                     attributes; a subsequent ListenerAttributeReader will scan each
 *                     class and produce the full listener data objects.
 *
 * `listeners`       — raw AST Expr nodes captured verbatim from getListeners();
 *                     the file generator writes them back out as-is so the exact
 *                     user-defined shape is preserved without re-interpretation.
 */
readonly class ListenerProviderResult
{
    /**
     * @param class-string[] $listenerClasses
     * @param Expr[]         $listeners        Raw AST expressions from getListeners()
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