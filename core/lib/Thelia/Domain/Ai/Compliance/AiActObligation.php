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

namespace Thelia\Domain\Ai\Compliance;

/**
 * The transparency duties of the EU AI Act a usage can fall under.
 */
enum AiActObligation: string
{
    /** Article 50(1): tell people they are talking with an AI. */
    case Interaction = 'article_50_1';
    /** Article 50(2): mark generated content as made by an AI, in a way a machine can read. */
    case GeneratedContent = 'article_50_2';
}
