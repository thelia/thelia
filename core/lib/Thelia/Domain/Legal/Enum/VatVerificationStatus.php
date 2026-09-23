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

namespace Thelia\Domain\Legal\Enum;

/**
 * What a verification service answered about an intra-community VAT number.
 *
 * The three cases are not interchangeable, and UNDETERMINED is the reason this
 * is not a boolean: a verification service that is down, throttled or simply
 * not installed has told us nothing, and treating that silence as a refusal
 * would deny a legitimate buyer, while treating it as a success would let
 * anyone claim an exemption. Only VERIFIED ever opens a right.
 */
enum VatVerificationStatus: string
{
    /** The service answered, and the number belongs to the country it declares. */
    case VERIFIED = 'verified';

    /** The service answered, and no business holds that number. */
    case REFUSED = 'refused';

    /** No answer: no verifier installed, service unreachable, or number out of its scope. */
    case UNDETERMINED = 'undetermined';
}
