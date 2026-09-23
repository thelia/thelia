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
 * Checks an intra-community VAT number against whatever authority a shop trusts.
 *
 * Thelia does not know how to reach such an authority, and does not want to:
 * the call is a module's business, with the throttling, the caching and the
 * outage handling that come with it. The core only defines what an answer looks
 * like, and ships a null implementation so the container builds, and so a shop
 * with no verification module exempts nobody.
 *
 * Format and checksum are already settled before this is called, by
 * CompanyIdentifierRules: an implementation answers about existence, not shape.
 *
 * An implementation must never throw for an unreachable service. An outage is
 * an UNDETERMINED answer, not a refusal, and it is returned rather than raised
 * because every caller sits on a path a customer is waiting on.
 *
 * Replace the default by aliasing this interface from a module
 * configureServices(), or with #[AsAlias] on the implementation.
 */
interface VatNumberVerifierInterface
{
    public function verify(string $vatNumber, string $countryIsoAlpha2): VatVerificationResult;
}
