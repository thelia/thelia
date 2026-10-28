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

use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Ai\Tool\AiToolDescriptor;
use Thelia\Domain\Ai\Tool\AiToolEffect;
use Thelia\Domain\Ai\Tool\AiToolRegistryInterface;
use Thelia\Domain\Ai\Tool\AiToolRisk;

/**
 * `tools/list`: the tools the registry offers to the administrator of the token, within the scopes of the token,
 * and nothing else. It replaces the handler of the SDK, which would list what any bundle registered.
 *
 * @implements RequestHandlerInterface<ListToolsResult>
 *
 * @internal
 */
final readonly class McpToolListHandler implements RequestHandlerInterface
{
    public function __construct(
        private AiToolRegistryInterface $registry,
        private McpToolContextFactory $contexts,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof ListToolsRequest;
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        $context = $this->contexts->create();

        if (null === $context) {
            return Error::forInvalidRequest('Unauthenticated', $request->getId());
        }

        $descriptors = [];

        foreach ($this->registry->describe() as $descriptor) {
            $descriptors[$descriptor->name] = $descriptor;
        }

        $tools = [];

        foreach ($this->registry->offer($context)->definitions as $definition) {
            $descriptor = $descriptors[$definition->name] ?? null;

            $tools[] = new Tool(
                $definition->name,
                null,
                $definition->inputSchema,
                $definition->description,
                $descriptor instanceof AiToolDescriptor ? $this->annotations($descriptor) : null,
            );
        }

        return new Response($request->getId(), new ListToolsResult($tools, null));
    }

    private function annotations(AiToolDescriptor $descriptor): ToolAnnotations
    {
        $reads = AiToolEffect::Read === $descriptor->effect;

        return new ToolAnnotations(
            null,
            $reads,
            AccessManager::DELETE === $descriptor->access,
            $reads,
            \in_array(AiToolRisk::ExternalCommunication, $descriptor->risks, true),
        );
    }
}
