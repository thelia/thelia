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
namespace Thelia\Core\Hook;

/**
 * Class HookDefinition.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookDefinition
{
    public const BASE_CLASS = 'Thelia\Core\Hook\BaseHook';

    public const RENDER_BLOCK_EVENT = 'Thelia\Core\Event\Hook\HookRenderBlockEvent';

    public const RENDER_FUNCTION_EVENT = 'Thelia\Core\Event\Hook\HookRenderEvent';
}
