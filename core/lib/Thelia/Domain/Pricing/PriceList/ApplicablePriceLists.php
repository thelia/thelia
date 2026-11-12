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

namespace Thelia\Domain\Pricing\PriceList;

use Propel\Runtime\Propel;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Domain\Pricing\CatalogActivityProbe;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\PriceListTableMap;

/**
 * The price lists that apply to a customer, in the order they are looked up, in one
 * statement per customer, group and currency, remembered for the request, and none while
 * no price list is turned on (the shop-wide probe answers that for free).
 *
 * A list applies when it is on, inside its period (a missing bound is open), and assigned to
 * the customer or to their group, in the currency asked. An exclusive list applies in every
 * currency: the sale elements it holds are the ones its customer may buy, whatever currency
 * they browse in, and it prices them only in its own. The group is the one
 * {@see CustomerGroupResolver} reads back from the database: a visitor, a console command
 * and a guest account belong to the default group, and a guest has no list of their own
 * either, a guest row being reusable by e-mail.
 *
 * The rank is the priority, the smallest first; a tie goes to the list assigned to the
 * customer rather than to their group, then to the oldest list.
 */
final class ApplicablePriceLists implements ResetInterface
{
    /** @var array<string, list<ApplicablePriceList>> */
    private array $memo = [];

    public function __construct(
        private readonly CustomerGroupResolver $customerGroupResolver,
        private readonly CatalogActivityProbe $catalogActivityProbe,
    ) {
    }

    /**
     * @return list<ApplicablePriceList>
     */
    public function for(?Customer $customer, Currency $currency): array
    {
        // No price list turned on: nothing to ask, whoever the customer is (the probe is read once per request).
        if (!$this->catalogActivityProbe->read()->hasPriceLists) {
            return [];
        }

        $customerId = $this->assignableCustomerId($customer);
        $groupId = $this->customerGroupResolver->groupIdOf($customer);
        $key = $customerId.'|'.$groupId.'|'.$currency->getId();

        return $this->memo[$key] ??= $this->read($customerId, $groupId, (int) $currency->getId());
    }

    /**
     * Whether any price list may price this customer: the guard a cart asks before pricing
     * its lines again.
     */
    public function anyFor(?Customer $customer, Currency $currency): bool
    {
        return [] !== $this->for($customer, $currency);
    }

    public function reset(): void
    {
        $this->memo = [];
    }

    private function assignableCustomerId(?Customer $customer): int
    {
        if (null === $customer || $customer->isNew() || 1 === (int) $customer->getIsGuest()) {
            return 0;
        }

        return (int) $customer->getId();
    }

    /**
     * @return list<ApplicablePriceList>
     */
    private function read(int $customerId, int $groupId, int $currencyId): array
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $statement = Propel::getConnection(PriceListTableMap::DATABASE_NAME)->prepare(
            'SELECT price_list.id AS id, price_list.code AS code, price_list.exclusive AS exclusive,
                    price_list.priority AS priority, price_list.currency_id AS currency_id,
                    MAX(assignment.customer_id IS NOT NULL) AS by_customer
             FROM `price_list` price_list
             INNER JOIN `price_list_assignment` assignment ON assignment.price_list_id = price_list.id
             WHERE price_list.active = 1
               AND (price_list.currency_id = :currency_id OR price_list.exclusive = 1)
               AND (price_list.starts_at IS NULL OR price_list.starts_at <= :starts_before)
               AND (price_list.ends_at IS NULL OR price_list.ends_at > :ends_after)
               AND (assignment.customer_group_id = :group_id OR assignment.customer_id = :customer_id)
             GROUP BY price_list.id, price_list.code, price_list.exclusive, price_list.priority, price_list.currency_id
             ORDER BY price_list.priority ASC, by_customer DESC, price_list.id ASC'
        );
        $statement->execute([
            'currency_id' => $currencyId,
            'starts_before' => $now,
            'ends_after' => $now,
            'group_id' => $groupId,
            'customer_id' => $customerId,
        ]);

        $lists = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $lists[] = new ApplicablePriceList(
                (int) $row['id'],
                (string) $row['code'],
                1 === (int) $row['exclusive'],
                (int) $row['priority'],
                1 === (int) $row['by_customer'],
                (int) $row['currency_id'],
            );
        }

        return $lists;
    }
}
