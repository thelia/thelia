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

namespace Thelia\Tests\Integration\Domain\Ai\Tool;

use Thelia\Core\Security\AccessManager;
use Thelia\Domain\Ai\Service\AiSettings;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenHasher;
use Thelia\Domain\Ai\Tool\Service\AiToolTokenService;
use Thelia\Tests\Support\Ai\AiToolTestCase;

/**
 * The ACL of Thelia reads a module code whatever its case (SecurityContext::isUserGranted lowercases it), and the
 * tools of a module are named after its code in lower case. A token scoped to that module must cover its tools the
 * same way, or it silently covers nothing.
 */
final class AiToolTokenModuleScopeCaseTest extends AiToolTestCase
{
    public function testAModuleScopeCoversTheToolsOfThatModuleWhateverTheCaseOfItsCode(): void
    {
        $admin = $this->createFixtureFactory()->admin(['login' => 'v6scope'.bin2hex(random_bytes(3))]);
        $tokens = new AiToolTokenService(new AiToolTokenHasher(), $this->access, new AiSettings(), $this->approvalRows);
        $created = $tokens->create($admin->getId(), 'module scope', [['module' => 'aiprobemodule', 'access' => AccessManager::VIEW]]);

        // The ACL of the administrator treats both spellings as the same module.
        self::assertTrue($this->access->adminHoldsRight($admin->getId(), null, 'aiprobemodule', AccessManager::VIEW));
        self::assertTrue($this->access->adminHoldsRight($admin->getId(), null, 'AiProbeModule', AccessManager::VIEW));

        // The tool aiprobemodule_modright declares module: 'AiProbeModule'.
        self::assertTrue(
            $this->access->tokenCovers($created->id, $admin->getId(), null, 'AiProbeModule', AccessManager::VIEW),
            'A token scoped to the module "aiprobemodule" does not cover the tool guarded by the module "AiProbeModule".',
        );
    }
}
