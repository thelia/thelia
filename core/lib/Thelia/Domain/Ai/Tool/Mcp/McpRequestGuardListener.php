<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Ai\Tool\Mcp;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns away, before the SDK reads them, the bodies the MCP endpoint (`POST /api/mcp`) does not serve: a JSON-RPC
 * batch, and a body over {@see self::MAX_BODY_BYTES}.
 *
 * A request carries one message. The rate limiter of the token counts requests, so a batch of a hundred calls
 * would run a hundred times its limit (the batching of JSON-RPC left MCP with the 2025-06-18 revision, which the
 * SDK still accepts for the older handshake).
 *
 * It runs just after the firewall of the tokens (priority 8): an anonymous caller gets its 401, or the 404 of an
 * endpoint that is off, without the body being read, and the request of a token was already counted by the limiter.
 *
 * @internal
 */
final class McpRequestGuardListener
{
    public const string PATH = '/api/mcp';

    /** What an `initialize` or a tool call needs, with room to spare: the SDK keeps what `initialize` sends in the session. */
    public const int MAX_BODY_BYTES = 262144;

    private const int INVALID_REQUEST = -32600;

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$request->isMethod(Request::METHOD_POST) || self::PATH !== $request->getPathInfo()) {
            return;
        }

        $response = $this->refusal($request);

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    private function refusal(Request $request): ?Response
    {
        $body = $request->getContent();

        if (\strlen($body) > self::MAX_BODY_BYTES) {
            return $this->error(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'The request is too large.');
        }

        if (str_starts_with(ltrim($body, " \t\r\n"), '[')) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'Batch requests are not supported: send one message per request.');
        }

        return null;
    }

    private function error(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => self::INVALID_REQUEST, 'message' => $message]], $status);
    }
}
