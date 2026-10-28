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

namespace AiProbeModule;

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Ai\Tool\AiApprovedChange;
use Thelia\Domain\Ai\Tool\AiChangeProposal;
use Thelia\Domain\Ai\Tool\AiReadToolInterface;
use Thelia\Domain\Ai\Tool\AiToolContext;
use Thelia\Domain\Ai\Tool\AiWriteToolInterface;
use Thelia\Domain\Ai\Tool\AsAiTool;

/**
 * Tools whose declaration breaks a rule of the registry (each is built by a test that expects the failure).
 */
trait ProbeBadReader
{
    public function inputSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
    }

    public function execute(array $arguments, AiToolContext $context): array
    {
        return [];
    }
}

#[AsAiTool(name: 'otherprefix_read', description: 'Prefix of another module.', resource: AdminResources::PRODUCT)]
final class ProbeWrongPrefixTool implements AiReadToolInterface
{
    use ProbeBadReader;
}

#[AsAiTool(name: 'thelia_read', description: 'Takes the name reserved to the core.', resource: AdminResources::PRODUCT)]
final class ProbeReservedPrefixTool implements AiReadToolInterface
{
    use ProbeBadReader;
}

#[AsAiTool(name: 'aiprobemodule_unknown_resource', description: 'A resource that is no constant of AdminResources.', resource: 'admin.does-not-exist')]
final class ProbeUnknownResourceTool implements AiReadToolInterface
{
    use ProbeBadReader;
}

#[AsAiTool(name: 'aiprobemodule_other_module', description: 'Guarded by the right of another module.', module: 'SomeOtherModule')]
final class ProbeOtherModuleTool implements AiReadToolInterface
{
    use ProbeBadReader;
}

#[AsAiTool(name: 'aiprobemodule_write_view', description: 'Writes with a read access.', resource: AdminResources::PRODUCT, access: AccessManager::VIEW)]
final class ProbeWriteWithViewTool implements AiWriteToolInterface
{
    public function inputSchema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'properties' => []];
    }

    public function propose(array $arguments, AiToolContext $context): AiChangeProposal
    {
        return new AiChangeProposal('x:1', 'x', [], []);
    }

    public function apply(AiApprovedChange $change): void
    {
    }
}

#[AsAiTool(name: 'aiprobemodule_read_update', description: 'Reads with a write access.', resource: AdminResources::PRODUCT, access: AccessManager::UPDATE)]
final class ProbeReadWithUpdateTool implements AiReadToolInterface
{
    use ProbeBadReader;
}

#[AsAiTool(name: 'aiprobemodule_both', description: 'Reads and writes.', resource: AdminResources::PRODUCT, access: AccessManager::UPDATE)]
final class ProbeBothTool implements AiReadToolInterface, AiWriteToolInterface
{
    use ProbeBadReader;

    public function propose(array $arguments, AiToolContext $context): AiChangeProposal
    {
        return new AiChangeProposal('x:1', 'x', [], []);
    }

    public function apply(AiApprovedChange $change): void
    {
    }
}

final class ProbeUndeclaredTool implements AiReadToolInterface
{
    use ProbeBadReader;
}

#[AsAiTool(name: 'aiprobemodule_read', description: 'The name of ProbeReadTool again.', resource: AdminResources::PRODUCT)]
final class ProbeDuplicateNameTool implements AiReadToolInterface
{
    use ProbeBadReader;
}
