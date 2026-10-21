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
 * - no postal code at all (null: no address yet) is accepted: the address decides later,
 *   and a missing code never hides every carrier;
 * - a placeholder (a typed code of only spaces and dashes, as "-") is not taken by a zone
 *   with an inclusion, and is taken by a zone with exclusions only;
 * - a code that cannot be checked (without a normal form, see ZipCodePattern::normalize(),
 *   or a pattern PCRE gives up on) is uncertain: an exclusion refuses it, an inclusion does
 *   not take it.
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
        // A zone without a rule takes every code: nothing to normalise.
        if ([] === $this->inclusions && [] === $this->exclusions) {
            return true;
        }

        // No code at all: the address decides later.
        if (ZipCodePattern::isUnknown($zipCode)) {
            return true;
        }

        // Typed, but no code: no inclusion takes it, and no exclusion names it.
        if (ZipCodePattern::isPlaceholder($zipCode)) {
            return [] === $this->inclusions;
        }

        // Normalised once for every rule. A code without a normal form is uncertain.
        $normalized = ZipCodePattern::normalize($zipCode);

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
