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

namespace Thelia\Core\Event\Legal;

use Thelia\Core\Event\ActionEvent;
use Thelia\Domain\Legal\VatVerificationResult;
use Thelia\Model\Address;

/**
 * A verification service has answered about the VAT number of one address.
 *
 * This is how a module hands its answer back: it reports, Thelia records. The
 * module never writes the columns itself, so the rule that only a verification
 * may set them lives in one place, and so a shop can listen for the answer to
 * tell its accounting or its CRM.
 *
 * The number is deliberately absent - it is read from the address, and the core
 * never logs it in clear.
 */
class VatNumberVerifiedEvent extends ActionEvent
{
    public function __construct(
        private readonly Address $address,
        private readonly VatVerificationResult $result,
    ) {
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getResult(): VatVerificationResult
    {
        return $this->result;
    }
}
