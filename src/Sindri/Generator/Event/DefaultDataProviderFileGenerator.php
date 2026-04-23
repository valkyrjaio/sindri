<?php

declare(strict_types=1);

/*
 * This file is part of the Valkyrja Framework package.
 *
 * (c) Melech Mizrachi <melechmizrachi@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sindri\Generator\Event;

use Override;
use Sindri\Generator\Container\Abstract\ProviderFileGenerator;
use Valkyrja\Event\Data\EventData;
use Valkyrja\Event\Provider\EventServiceProvider;

class DefaultDataProviderFileGenerator extends ProviderFileGenerator
{
    /**
     * @param non-empty-string $directory The directory
     * @param non-empty-string $namespace The class namespace
     * @param non-empty-string $className The class name
     */
    public function __construct(
        string $directory,
        string $namespace,
        string $className,
    ) {
        parent::__construct(
            directory: $directory,
            namespace: $namespace,
            className: $className,
            serviceClassName: 'EventData',
            serviceFullNamespace: EventData::class,
            publishMethod: 'publishData',
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getImports(): string
    {
        $serviceProvider = EventServiceProvider::class;

        return <<<PHP
            use $serviceProvider;
            PHP;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getPublishContents(): string
    {
        return <<<'PHP'
            EventServiceProvider::publishData($container);
            PHP;
    }
}
