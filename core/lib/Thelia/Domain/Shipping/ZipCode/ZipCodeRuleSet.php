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
 * - an unknown postal code (none, or only spaces) is accepted: the address decides later,
 *   and a missing code never hides every carrier;
 * - a code that cannot be checked (not valid UTF-8, emptied by its normal form as a
 *   zero-width space alone is, or a pattern PCRE gives up on) is uncertain: an exclusion
 *   refuses it, an inclusion does not take it.
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
        // Unknown: no code, or only spaces. A code its normal form cannot read or empties is
        // uncertain, and goes on to the rules.
        if (ZipCodePattern::isUnknown($zipCode)) {
            return true;
        }

        // Normalised once for every rule.
        $normalized = ZipCodePattern::normalize((string) $zipCode);

        // An exclusion that cannot be checked refuses the code: it fails closed.
        foreach ($this->exclusions as $exclusion) {
            if ($exclusion->matchesNormalized($normalized, whenUnsure: true)) {
                return false;
            }
        }

        if ([] === $this->inclusions) {
            return true;
        }

        foreach ($this->inclusions as $inclusion) {
            if ($inclusion->matchesNormalized($normalized)) {
                return true;
            }
        }

        return false;
    }
}
