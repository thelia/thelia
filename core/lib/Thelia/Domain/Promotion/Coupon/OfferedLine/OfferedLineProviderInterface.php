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

namespace Thelia\Domain\Promotion\Coupon\OfferedLine;

use Thelia\Domain\Promotion\Coupon\FacadeInterface;

/**
 * A coupon type whose effect places offered lines in the cart.
 *
 * The coupon only DESCRIBES the lines it wants: the writes are done by
 * OfferedCartLineService after the evaluation, never by exec() itself.
 */
interface OfferedLineProviderInterface
{
    /**
     * @return OfferedLineRequest[] the offered lines the current cart state entitles the customer to
     */
    public function getOfferedLineRequests(FacadeInterface $facade): array;
}
