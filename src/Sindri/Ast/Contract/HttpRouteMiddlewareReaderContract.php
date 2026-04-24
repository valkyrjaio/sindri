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

use PhpParser\Node\Arg;
use PhpParser\Node\Stmt\ClassMethod;
use Sindri\Ast\Data\HttpRouteData;

/**
 * Reads middleware, request methods, and struct attributes for HTTP routes, and builds
 * their corresponding AST expressions.
 */
interface HttpRouteMiddlewareReaderContract
{
    /**
     * Extract the inline requestMethods array from a #[Route] attribute (positional arg 3).
     *
     * @param Arg[]                 $args
     * @param array<string, string> $useMap
     *
     * @return string[]
     */
    public function extractInlineRequestMethods(
        array $args,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array;

    /**
     * Collect request methods from #[Route\RequestMethod] and shorthand sub-attributes.
     *
     * @param string[]              $requestMethods Already collected methods (from inline arg)
     * @param array<string, string> $useMap
     *
     * @return string[]
     */
    public function updateRequestMethods(
        array $requestMethods,
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): array;

    /**
     * Collect and classify #[Route\Middleware] attributes into the five middleware lists.
     *
     * @param array<string, string> $useMap
     * @param class-string[]        $routeMatchedMiddleware
     * @param class-string[]        $routeDispatchedMiddleware
     * @param class-string[]        $throwableCaughtMiddleware
     * @param class-string[]        $sendingResponseMiddleware
     * @param class-string[]        $terminatedMiddleware
     *
     * @return array{class-string[], class-string[], class-string[], class-string[], class-string[]}
     */
    public function updateMiddleware(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
        array $routeMatchedMiddleware,
        array $routeDispatchedMiddleware,
        array $throwableCaughtMiddleware,
        array $sendingResponseMiddleware,
        array $terminatedMiddleware,
    ): array;

    /**
     * Resolve the request struct FQN from #[Route\RequestStruct], if any.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string|null
     */
    public function updateRequestStruct(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string|null;

    /**
     * Resolve the response struct FQN from #[Route\ResponseStruct], if any.
     *
     * @param array<string, string> $useMap
     *
     * @return class-string|null
     */
    public function updateResponseStruct(
        ClassMethod $method,
        array $useMap,
        string $namespace,
        string $currentClass,
    ): string|null;

    /**
     * Build the middleware named-arg list for an HttpRouteData.
     *
     * @return Arg[]
     */
    public function buildRouteMiddlewareArgs(HttpRouteData $data): array;

    /**
     * Build the requestStruct/responseStruct named-arg list for an HttpRouteData.
     *
     * @return Arg[]
     */
    public function buildRouteStructArgs(HttpRouteData $data): array;
}