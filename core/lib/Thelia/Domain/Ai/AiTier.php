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

namespace Thelia\Domain\Ai;

/**
 * The range of model a usage asks for. A module never names a model: the merchant assigns one model
 * to each tier, once, and may pin another one for a single usage.
 */
enum AiTier: string
{
    /** Cheap and quick: chat, classification, short answers. */
    case Fast = 'fast';
    case Standard = 'standard';
    /** The strongest model the merchant pays for: long generation, tool loops. */
    case Advanced = 'advanced';
}
