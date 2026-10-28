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
 * The transparency duty of a usage under the EU AI Act, declared by the module. The core gives the tools
 * (notice, provenance mark), turns them on by default and explains; it never blocks. The merchant may turn
 * them off: the usage then shows as not compliant (AiUsageRegistryInterface::compliance()).
 */
enum AiTransparency: string
{
    /** Nothing to disclose: an internal job, an administrator tool that publishes nothing. */
    case None = 'none';
    /**
     * Article 50(1): a person talks with the system. The core provides the notice "you are talking with an
     * AI", shown by default.
     */
    case Interactive = 'interactive';
    /**
     * Article 50(2): the system generates content that will be published. Each response carries an
     * AiProvenance the module stores with the content, unless the merchant turned the mark off.
     */
    case Generative = 'generative';
}
