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

/**
 * Services extracted from a single ServiceProviderContract implementation.
 *
 * `serviceClasses` contains the keys of the `publishers()` return array —
 * i.e. the fully-qualified class names of every service the provider publishes.
 */
readonly class ServiceProviderResult
{
    /**
     * @param class-string[] $serviceClasses
     */
    public function __construct(
        public array $serviceClasses = [],
    ) {
    }

    /**
     * Merge another result into this one, deduplicating the service list.
     */
    public function merge(self $other): self
    {
        return new self(
            serviceClasses: array_values(array_unique([...$this->serviceClasses, ...$other->serviceClasses])),
        );
    }
}