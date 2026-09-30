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

namespace Thelia\Domain\QuickOrder;

use Propel\Runtime\Propel;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Cart\DTO\CartItemAddDTO;
use Thelia\Domain\Catalog\DTO\ReferenceQuantityLines;
use Thelia\Domain\CustomerList\PurchaseListFacade;
use Thelia\Domain\QuickOrder\DTO\QuickOrderTable;
use Thelia\Domain\QuickOrder\Enum\LineStatus;
use Thelia\Domain\QuickOrder\Exception\QuickOrderCartNotFoundException;
use Thelia\Domain\QuickOrder\Exception\QuickOrderRateLimitedException;
use Thelia\Domain\QuickOrder\Service\QuickOrderLimiter;
use Thelia\Domain\QuickOrder\Service\ReferenceResolver;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartTableMap;

/**
 * Ordering by reference: the one entry point the API and the theme call.
 *
 * resolve() writes nothing, which is what lets the buyer review a control table
 * before the cart changes. addToCart() resolves the lines again rather than trust
 * the table the browser holds, then adds the resolved ones through CartFacade, the
 * way a product page does, in one transaction: either every resolved line is in
 * the cart, or none is.
 *
 * Every entry point spends the account's quick order budget before it reads
 * anything, so the API and the theme share one limit and a list of another account
 * costs the same as a list of one's own.
 *
 * Products hidden by a reserved sale follow the customer signed in to the current
 * request, not the customer given here (see ReferenceResolver). Both are the same
 * for the API and the theme.
 */
final readonly class QuickOrderFacade
{
    public function __construct(
        private ReferenceResolver $resolver,
        private CartFacade $cartFacade,
        private PurchaseListFacade $purchaseListFacade,
        private QuickOrderLimiter $limiter,
    ) {
    }

    /**
     * @throws QuickOrderRateLimitedException
     */
    public function resolve(Customer $customer, ReferenceQuantityLines $lines, Currency $currency): QuickOrderTable
    {
        $this->spendBudget($customer);

        return $this->resolver->resolve($customer, $lines, $currency);
    }

    /**
     * Loads a purchase list into the control table, in the format resolve() answers.
     *
     * @throws QuickOrderRateLimitedException
     * @throws \Thelia\Domain\CustomerList\Exception\PurchaseListNotFoundException
     */
    public function resolvePurchaseList(Customer $customer, int $listId, Currency $currency): QuickOrderTable
    {
        $this->spendBudget($customer);

        return $this->resolver->resolve(
            $customer,
            new ReferenceQuantityLines($this->purchaseListFacade->linesToLoad($customer, $listId)),
            $currency,
        );
    }

    /**
     * @throws QuickOrderRateLimitedException
     * @throws QuickOrderCartNotFoundException
     */
    public function addToCart(Customer $customer, Cart $cart, ReferenceQuantityLines $lines): QuickOrderTable
    {
        $this->spendBudget($customer);

        if ((int) $cart->getCustomerId() !== (int) $customer->getId()) {
            throw new QuickOrderCartNotFoundException('Cart not found.');
        }

        $table = $this->resolver->resolve($customer, $lines, $cart->getCurrency() ?? Currency::getDefaultCurrency());
        $connection = Propel::getWriteConnection(CartTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $tableLines = [];

            foreach ($table->lines as $line) {
                if (LineStatus::Resolved !== $line->status) {
                    $tableLines[] = $line;

                    continue;
                }

                $this->cartFacade->addItem(new CartItemAddDTO(
                    $cart,
                    (int) $line->productId,
                    (int) $line->productSaleElementsId,
                    $line->quantity,
                ));
                $tableLines[] = $line->markAdded();
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return new QuickOrderTable($tableLines);
    }

    private function spendBudget(Customer $customer): void
    {
        if (!$this->limiter->allows($customer)) {
            throw new QuickOrderRateLimitedException('Too many quick order requests, please try again in a minute.');
        }
    }
}
