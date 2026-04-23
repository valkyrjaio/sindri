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

namespace Sindri\Ast\Data;

use Sindri\Ast\Data\Contract\HttpParameterDataContract;

/**
 * Portable intermediate representation of an HTTP route parameter extracted
 * from #[Parameter] attributes.
 *
 * The cast field is stored as a "FQN::CASE" string so the file generator can
 * write it verbatim without evaluating it.
 */
readonly class HttpParameterData implements HttpParameterDataContract
{
    /**
     * @param non-empty-string $name          Parameter name
     * @param non-empty-string $regex         Regex pattern for the parameter
     * @param string|null      $cast          "FQN::CASE" of the Cast value, or null
     * @param bool             $isOptional    Whether the parameter is optional
     * @param bool             $shouldCapture Whether the parameter value should be captured
     */
    public function __construct(
        public string $name,
        public string $regex,
        public string|null $cast = null,
        public bool $isOptional = false,
        public bool $shouldCapture = true,
    ) {
    }
}
