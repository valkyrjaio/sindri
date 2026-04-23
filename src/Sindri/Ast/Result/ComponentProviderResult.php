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
 * Provider lists extracted from a single ComponentProviderContract source file.
 *
 * The orchestrator is responsible for:
 *   1. Resolving each class name in `componentProviders` to a file path.
 *   2. Calling ComponentProviderReader::readFile() on each of those files.
 *   3. Merging the results into the aggregated collection.
 */
readonly class ComponentProviderResult
{
    /**
     * @param class-string[] $componentProviders Sub-component providers (getComponentProviders)
     * @param class-string[] $serviceProviders   Container/service providers (getContainerProviders)
     * @param class-string[] $listenerProviders  Event listener providers (getEventProviders)
     * @param class-string[] $cliRouteProviders  CLI route providers (getCliProviders)
     * @param class-string[] $httpRouteProviders HTTP route providers (getHttpProviders)
     */
    public function __construct(
        public array $componentProviders = [],
        public array $serviceProviders = [],
        public array $listenerProviders = [],
        public array $cliRouteProviders = [],
        public array $httpRouteProviders = [],
    ) {
    }

    /**
     * Merge another result into this one, deduplicating each list.
     */
    public function merge(self $other): self
    {
        return new self(
            componentProviders: array_values(array_unique([...$this->componentProviders, ...$other->componentProviders])),
            serviceProviders: array_values(array_unique([...$this->serviceProviders, ...$other->serviceProviders])),
            listenerProviders: array_values(array_unique([...$this->listenerProviders, ...$other->listenerProviders])),
            cliRouteProviders: array_values(array_unique([...$this->cliRouteProviders, ...$other->cliRouteProviders])),
            httpRouteProviders: array_values(array_unique([...$this->httpRouteProviders, ...$other->httpRouteProviders])),
        );
    }
}
