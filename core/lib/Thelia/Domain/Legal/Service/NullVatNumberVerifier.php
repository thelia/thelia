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

namespace Thelia\Domain\Legal\Service;

use Thelia\Domain\Legal\VatVerificationResult;

/**
 * Default implementation used when no verification module is installed or active.
 *
 * It answers UNDETERMINED to everything, which is what makes the VAT exemption
 * inert out of the box: no address is ever verified, so no order is ever
 * exempted, whatever the shop configured. It is also the implementation the
 * test suite runs against, so that no test reaches out to the Internet.
 *
 * The interface alias pointing to this class is registered in
 * Config/Resources/services/core/legal.php so an active module can override it
 * with `#[AsAlias(VatNumberVerifierInterface::class)]`.
 */
final class NullVatNumberVerifier implements VatNumberVerifierInterface
{
    public function verify(string $vatNumber, string $countryIsoAlpha2): VatVerificationResult
    {
        return VatVerificationResult::undetermined();
    }
}
