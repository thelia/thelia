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

namespace Thelia\Model;

use Thelia\Model\Base\OrderConsent as BaseOrderConsent;

class OrderConsent extends BaseOrderConsent
{
    public function isAccepted(): bool
    {
        return 1 === $this->getAccepted();
    }
}
