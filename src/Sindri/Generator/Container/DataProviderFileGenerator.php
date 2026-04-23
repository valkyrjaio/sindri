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

namespace Sindri\Generator\Container;

use Override;
use Sindri\Generator\Container\Abstract\ProviderFileGenerator;
use Valkyrja\Container\Data\ContainerData;

class DataProviderFileGenerator extends ProviderFileGenerator
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
        protected string $dataClassNamespace,
        protected string $dataClassName,
    ) {
        parent::__construct(
            directory: $directory,
            namespace: $namespace,
            className: $className,
            serviceClassName: 'ContainerData',
            serviceFullNamespace: ContainerData::class,
            publishMethod: 'publishData',
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function getImports(): string
    {
        $serviceProvider = $this->dataClassNamespace;

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
        $dataClassName = $this->dataClassName;

        return <<<PHP
            \$container->setSingleton(ContainerData::class, new $dataClassName());
            PHP;
    }
}
