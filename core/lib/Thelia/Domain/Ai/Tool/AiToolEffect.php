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

namespace Thelia\Domain\Ai\Tool;

/**
 * What a tool does, deduced from the interface it implements and never declared.
 */
enum AiToolEffect: string
{
    case Read = 'read';
    case Write = 'write';
}
