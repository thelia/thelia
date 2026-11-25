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

namespace Thelia\Domain\Quote\Service;

use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Model\QuoteRevision;

/**
 * A revision that was sent is what the buyer was shown: nothing writes to it any more.
 */
final readonly class QuoteRevisionGuard
{
    /**
     * @throws QuoteDraftRefusedException when the revision is not a draft
     */
    public function assertDraft(QuoteRevision $revision): void
    {
        if (!$revision->isNew() && QuoteRevisionState::Draft->value !== $revision->getState()) {
            throw QuoteDraftRefusedException::revisionLocked();
        }
    }
}
