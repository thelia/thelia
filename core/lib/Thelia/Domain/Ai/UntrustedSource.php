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
 * Where a piece of untrusted content comes from. The gateway names the source in the delimiters it wraps
 * the content in, and tells the model never to follow an instruction found inside.
 */
enum UntrustedSource: string
{
    /** A page read on the web. */
    case WebPage = 'web_page';
    /** Text a visitor or a customer typed: a review, a message, a search. */
    case VisitorInput = 'visitor_input';
    /** An offer of a competitor: title, description, seller. */
    case CompetitorOffer = 'competitor_offer';
    /** A file or a message received from a third party. */
    case Document = 'document';
}
