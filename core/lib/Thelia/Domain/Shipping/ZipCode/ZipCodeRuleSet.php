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

namespace Thelia\Domain\Shipping\ZipCode;

/**
 * The postal code rules of a delivery zone for one country of it.
 *
 * - no rule: every postal code is accepted, as a zone always did;
 * - a code matching an exclusion is refused, whatever the inclusions say;
 * - when the zone has inclusions, a code must match one of them;
 * - an unknown postal code is accepted: the address decides later, and a missing code
 *   never hides every carrier.
 */
final readonly class ZipCodeRuleSet
{
    /**
     * @param list<ZipCodePattern> $inclusions
     * @param list<ZipCodePattern> $exclusions
     */
    public function __construct(
        private array $inclusions = [],
        private array $exclusions = [],
    ) {
    }

    public function accepts(?string $zipCode): bool
    {
        if (null === $zipCode || '' === ZipCodePattern::normalize($zipCode)) {
            return true;
        }

        // An exclusion that cannot be checked refuses the code: it fails closed.
        foreach ($this->exclusions as $exclusion) {
            if ($exclusion->matches($zipCode, whenUnsure: true)) {
                return false;
            }
        }

        if ([] === $this->inclusions) {
            return true;
        }

        foreach ($this->inclusions as $inclusion) {
            if ($inclusion->matches($zipCode)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return [] === $this->inclusions && [] === $this->exclusions;
    }
}
