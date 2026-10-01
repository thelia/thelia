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
use Thelia\Model\CartItemQuery;
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
 * the cart, or none is. A line is only reported added once the cart holds it: the
 * resolver checks a line against the stock alone, and the cart keeps its quantity
 * without a word when what it already holds leaves too little stock for the rest.
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
            $inCart = $this->quantitiesIn($cart);

            foreach ($table->lines as $line) {
                if (LineStatus::Resolved !== $line->status) {
                    $tableLines[] = $line;

                    continue;
                }

                $item = $this->cartFacade->addItem(new CartItemAddDTO(
                    $cart,
                    (int) $line->productId,
                    (int) $line->productSaleElementsId,
                    $line->quantity,
                ));
                $before = $inCart[(int) $item->getId()] ?? 0.0;
                $inCart[(int) $item->getId()] = (float) $item->getQuantity();

                $tableLines[] = $inCart[(int) $item->getId()] - $before >= $line->quantity
                    ? $line->markAdded()
                    : $line->refusedByTheCart((float) $item->getProductSaleElements()->getQuantity() - $before);
            }

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        return new QuickOrderTable($tableLines);
    }

    /**
     * The quantity of each line of the cart, by line: the cart (or a module answering
     * CART_FINDITEM) picks the line a sale element is added to, so that line is the one
     * compared before and after.
     *
     * @return array<int, float>
     */
    private function quantitiesIn(Cart $cart): array
    {
        $quantities = [];

        foreach (CartItemQuery::create()->filterByCartId($cart->getId())->find() as $item) {
            $quantities[(int) $item->getId()] = (float) $item->getQuantity();
        }

        return $quantities;
    }

    private function spendBudget(Customer $customer): void
    {
        if (!$this->limiter->allows($customer)) {
            throw new QuickOrderRateLimitedException('Too many quick order requests, please try again in a minute.');
        }
    }
}
