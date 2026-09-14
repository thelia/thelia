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

namespace Thelia\Domain\Checkout\Service;

/**
 * What the buyer has agreed to, whatever holds the answers.
 *
 * The checkout ships one implementation, ConsentAcceptanceStore, which reads the session
 * — that is where an answer lives while the order is still being placed. The interface
 * exists because the reading is also done outside a browser: the progression is asked
 * whether a cart is ready to pay from the API and from a command line too, and there the
 * answers come from somewhere else. Binding this interface to another service replaces
 * the source everywhere the checkout asks the question, without any caller knowing.
 */
interface ConsentAcceptanceReaderInterface
{
    /**
     * @return array<string, bool> the answer given to each consent, by consent code
     */
    public function all(): array;
}
