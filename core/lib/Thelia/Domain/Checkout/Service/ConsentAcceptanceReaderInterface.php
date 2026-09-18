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
 * What the checkout is bound to is ConsentAnswerChain, which asks the two sources the
 * core ships in the one order that lets them work side by side: the answers stated in
 * the request that places the order, then the session a buyer walking the screens of a
 * theme ticked their boxes in. The interface exists because neither has to be the
 * source: binding it to another service replaces them everywhere the checkout asks the
 * question, without any caller knowing, and all it has to answer is this one method.
 */
interface ConsentAcceptanceReaderInterface
{
    /**
     * @return array<string, bool> the answer given to each consent, by consent code
     */
    public function all(): array;
}
