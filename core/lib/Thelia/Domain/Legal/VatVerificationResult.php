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

namespace Thelia\Domain\Legal;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Legal\Enum\VatVerificationStatus;

/**
 * The answer a verification service gave about one VAT number, at one moment.
 *
 * The moment is part of the answer: a shop exempts on a verification that is
 * still recent, so a result without its date could not be aged out. The name
 * is what the service returned for the business, when it returns one at all -
 * VIES, for instance, only discloses it for some member states.
 *
 * The number itself is deliberately absent: this object travels through events
 * and listeners, and a VAT number identifies a business as precisely as its
 * name does, so it stays with the address it was read from.
 */
#[Exclude]
final readonly class VatVerificationResult
{
    private function __construct(
        public VatVerificationStatus $status,
        public ?\DateTimeImmutable $verifiedAt = null,
        public ?string $verifiedName = null,
    ) {
    }

    public static function verified(\DateTimeImmutable $verifiedAt, ?string $verifiedName = null): self
    {
        return new self(VatVerificationStatus::VERIFIED, $verifiedAt, $verifiedName);
    }

    public static function refused(\DateTimeImmutable $verifiedAt): self
    {
        return new self(VatVerificationStatus::REFUSED, $verifiedAt);
    }

    public static function undetermined(): self
    {
        return new self(VatVerificationStatus::UNDETERMINED);
    }

    public function isVerified(): bool
    {
        return VatVerificationStatus::VERIFIED === $this->status;
    }
}
