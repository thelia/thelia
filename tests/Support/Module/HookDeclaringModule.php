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

namespace Thelia\Tests\Support\Module;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;

/**
 * A module that declares one back-office block hook, as a module adds a tab
 * to its own edit screen.
 */
final class HookDeclaringModule extends BaseModule
{
    public const string HOOK_CODE = 'hook_declaring_probe.additional';

    public function getHooks(): array
    {
        return [
            [
                'type' => TemplateDefinition::BACK_OFFICE,
                'code' => self::HOOK_CODE,
                'title' => ['en_US' => 'Probe extra tab'],
                'active' => true,
                'block' => true,
            ],
        ];
    }
}
