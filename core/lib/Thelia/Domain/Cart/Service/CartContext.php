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

namespace Thelia\Domain\Cart\Service;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

class CartContext
{
    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcher $eventDispatcher,
    ) {
    }

    public function clearCartSession(): void
    {
        $this->requestStack->getCurrentRequest()?->getSession()->clearSessionCart($this->eventDispatcher);
    }
}
