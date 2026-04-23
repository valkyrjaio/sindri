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

namespace Sindri\Ast\Contract;

use Sindri\Ast\Result\ListenerAttributeResult;

/**
 * Reads #[Listener] and #[ListenerHandler] attributes from a listener class file.
 */
interface ListenerAttributeReaderContract
{
    /**
     * Scan a listener class source file and return all extracted listener data objects.
     */
    public function readFile(string $filePath): ListenerAttributeResult;
}
