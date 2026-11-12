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

namespace Thelia\Tests\Integration\Domain\Pricing\PriceList;

use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\CustomerGroup;
use Thelia\Model\PriceList;
use Thelia\Model\PriceListAssignment;
use Thelia\Model\PriceListItem;
use Thelia\Model\Product;
use Thelia\Model\ProductSaleElements;

/**
 * Rows of price lists for the tests that price against them.
 */
trait PriceListFixtures
{
    private int $priceListSequence = 0;

    protected function priceList(
        ?string $code = null,
        ?Currency $currency = null,
        int $priority = 100,
        bool $exclusive = false,
        ?\DateTimeInterface $startsAt = null,
        ?\DateTimeInterface $endsAt = null,
        bool $active = true,
    ): PriceList {
        $list = (new PriceList())
            ->setCode($code ?? 'LIST-'.(++$this->priceListSequence).'-'.bin2hex(random_bytes(3)))
            ->setCurrencyId(($currency ?? Currency::getDefaultCurrency())->getId())
            ->setPriority($priority)
            ->setExclusive($exclusive ? 1 : 0)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setActive($active ? 1 : 0);
        $list->save($this->getPropelConnection());

        return $list;
    }

    protected function priceListItem(PriceList $list, ProductSaleElements $pse, ?string $price, int $minQuantity = 1, ?string $discountPercent = null): PriceListItem
    {
        $item = (new PriceListItem())
            ->setPriceListId($list->getId())
            ->setProductSaleElementsId($pse->getId())
            ->setMinQuantity($minQuantity)
            ->setPrice($price)
            ->setDiscountPercent($discountPercent);
        $item->save($this->getPropelConnection());

        return $item;
    }

    protected function assignToGroup(PriceList $list, CustomerGroup $group): void
    {
        (new PriceListAssignment())->setPriceListId($list->getId())->setCustomerGroupId($group->getId())->save($this->getPropelConnection());
    }

    protected function assignToCustomer(PriceList $list, Customer $customer): void
    {
        (new PriceListAssignment())->setPriceListId($list->getId())->setCustomerId($customer->getId())->save($this->getPropelConnection());
    }

    protected function pricedProduct(float $price = 100.0): Product
    {
        return $this->factory->product(
            $this->factory->category(),
            $this->factory->taxRule(),
            Currency::getDefaultCurrency(),
            ['basePrice' => $price, 'baseQuantity' => 100],
        );
    }

    protected function memberOf(CustomerGroup $group, float $discount = 0.0): Customer
    {
        $member = $this->factory->customer($this->factory->customerTitle(), ['customerGroup' => $group]);

        if ($discount > 0.0) {
            $member->setDiscount((string) $discount)->save($this->getPropelConnection());
        }

        return $member;
    }

    /**
     * What the request would have forgotten between two reads.
     */
    protected function forgetRequestMemos(): void
    {
        $this->getService('services_resetter')->reset();
    }
}
