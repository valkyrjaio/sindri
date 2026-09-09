<?php

declare(strict_types=1);

/*
 * This file is part of the Sindri package.
 *
 * Copyright (c) 2016-present Melech Mizrachi
 *
 * Released under the MIT License. See LICENSE.md for details.
 */

namespace Sindri\Tests\Fixtures\Provider;

use Override;
use Sindri\Tests\Fixtures\Provider\Sub\TestServiceProviderFixture;
use Sindri\Tests\Fixtures\Provider\Sub\TestSubComponentProviderFixture;
use Valkyrja\Application\Kernel\Contract\ApplicationContract;
use Valkyrja\Application\Provider\Abstract\ComponentProvider;

final class TestComponentProviderFixture extends ComponentProvider
{
    #[Override]
    public function getComponentProviders(ApplicationContract $app): array
    {
        return [new TestSubComponentProviderFixture()];
    }

    #[Override]
    public function getContainerProviders(ApplicationContract $app): array
    {
        return [new TestServiceProviderFixture()];
    }
}
