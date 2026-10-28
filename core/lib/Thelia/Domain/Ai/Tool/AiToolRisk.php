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
 * The three legs of the lethal trifecta: an agent that holds all three can be turned against the shop by a
 * text it reads. The core reports the combination on an offer; it never blocks it.
 */
enum AiToolRisk: string
{
    /** The tool returns data that is not public: stock, purchase prices, customers, orders. */
    case PrivateData = 'private_data';
    /** The tool returns text a third party wrote: reviews, web pages, messages. */
    case UntrustedContent = 'untrusted_content';
    /** The tool sends something out of the shop: an e-mail, a message, a request to another system. */
    case ExternalCommunication = 'external_communication';
}
