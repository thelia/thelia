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

namespace Thelia\Api\Service\Quote;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Model\Customer;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\QuoteQuery;

/**
 * The quotes of the storefront an account may reach, as a condition of the query: its own and, when
 * its role reads the orders of the company, the ones of its company, and never a draft of the shop,
 * which the buyer does not know exists.
 *
 * The same rule as {@see QuoteAccess::canRead()}, written for a query: it bounds the collection, the
 * item and the quote an operation loads by its number alike, so that what is out of reach reads as
 * "not found", the answer a missing number gives.
 */
final readonly class QuoteOwnershipScope
{
    public function __construct(
        private QuoteAccess $access,
    ) {
    }

    public function apply(QuoteQuery $query, ?Customer $customer): void
    {
        if (null === $customer) {
            // quote.id is the primary key: this criterion matches no row.
            $query->add(QuoteTableMap::COL_ID, null, Criteria::ISNULL);

            return;
        }

        // Added to what the filters of the request already say about the state, never in place of it.
        $query->addAnd(QuoteTableMap::COL_STATE, QuoteState::Draft->value, Criteria::NOT_EQUAL);

        $customerId = (int) $customer->getId();
        $reachable = $query->getNewCriterion(QuoteTableMap::COL_CUSTOMER_ID, $customerId, Criteria::EQUAL);
        $companyId = $this->access->visibleCompanyId($customerId);

        if (null !== $companyId) {
            $reachable->addOr($query->getNewCriterion(QuoteTableMap::COL_COMPANY_ID, $companyId, Criteria::EQUAL));
        }

        $query->addAnd($reachable);
    }
}
