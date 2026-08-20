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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Cart\CartItemEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorResolverTrait;
use Thelia\Model\Base\CartItem as BaseCartItem;

class CartItem extends BaseCartItem
{
    use TaxCalculatorResolverTrait;

    protected ?EventDispatcherInterface $dispatcher = null;

    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        parent::preInsert($con);

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch($cartItemEvent, TheliaEvents::CART_ITEM_CREATE_BEFORE);
        }

        return true;
    }

    public function preUpdate(?ConnectionInterface $con = null): bool
    {
        parent::preUpdate($con);

        if ($this->dispatcher) {
            $cartItemEvent = new CartItemEvent($this);
            $this->dispatcher->dispatch($cartItemEvent, TheliaEvents::CART_ITEM_UPDATE_BEFORE);
        }

        return true;
    }

    /**
     * @throws PropelException
     */
    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch($cartEvent, TheliaEvents::AFTER_CARTADDITEM);
        }
    }

    /**
     * @throws PropelException
     */
    public function postUpdate(?ConnectionInterface $con = null): void
    {
        parent::postUpdate($con);

        if ($this->dispatcher) {
            $cartEvent = new CartEvent($this->getCart());

            $this->dispatcher->dispatch($cartEvent, TheliaEvents::AFTER_CARTUPDATEITEM);
        }
    }

    /**
     * @return $this
     *
     * @throws PropelException
     * @throws NotEnoughStockException
     */
    public function updateQuantity($value)
    {
        $currentQuantity = $this->getQuantity();

        if ($value <= 0) {
            $value = $currentQuantity;
        }

        if (ConfigQuery::checkAvailableStock()) {
            $productSaleElements = $this->getProductSaleElements();
            $product = $productSaleElements->getProduct();

            if (0 === $product->getVirtual() && $productSaleElements->getQuantity() < $value) {
                $this->setQuantity($currentQuantity);
                throw new NotEnoughStockException(Translator::getInstance()->trans('Not enough stock for product '.$product->getRef()));
            }
        }

        $this->setQuantity($value);

        return $this;
    }

    /**
     * @return $this
     *
     * @throws PropelException
     */
    public function addQuantity($value)
    {
        $currentQuantity = $this->getQuantity();
        $newQuantity = $currentQuantity + $value;

        if (ConfigQuery::checkAvailableStock()) {
            $productSaleElements = $this->getProductSaleElements();
            $product = $productSaleElements->getProduct();

            if (0 === $product->getVirtual() && $productSaleElements->getQuantity() < $newQuantity) {
                $newQuantity = $currentQuantity;
            }
        }

        $this->setQuantity($newQuantity);

        return $this;
    }

    public function getRealPrice(): float
    {
        return (float) (1 === (int) $this->getPromo() ? $this->getPromoPrice() : $this->getPrice());
    }

    /**
     * @throws PropelException
     */
    public function getProduct(?ConnectionInterface $con = null, $locale = null): Product
    {
        $product = parent::getProduct($con);
        if (null === $locale) {
            /** @var string $locale */
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $translation = $product->getTranslation($locale);

        if ($translation->isNew() && Lang::REPLACE_BY_DEFAULT_LANGUAGE === ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            $locale = Lang::getDefaultLanguage()->getLocale();
        }

        $product->setLocale($locale);

        return $product;
    }

    /**
     * @throws PropelException
     */
    public function getRealTaxedPrice(Country $country, ?State $state = null): float
    {
        return 1 === (int) $this->getPromo() ? $this->getTaxedPromoPrice($country, $state) : $this->getTaxedPrice($country, $state);
    }

    /**
     * @throws PropelException
     */
    public function getTaxedPrice(Country $country, ?State $state = null): float
    {
        return $this->createTaxCalculator()->load($this->getProduct(), $country, $state)->getTaxedPrice((float) $this->getPrice());
    }

    /**
     * @throws PropelException
     */
    public function getTaxedPromoPrice(Country $country, ?State $state = null): float
    {
        return $this->createTaxCalculator()->load($this->getProduct(), $country, $state)->getTaxedPrice((float) $this->getPromoPrice());
    }

    /**
     * @throws PropelException
     */
    public function getTotalRealTaxedPrice(Country $country, ?State $state = null): float
    {
        return 1 === (int) $this->getPromo() ? $this->getTotalTaxedPromoPrice($country, $state) : $this->getTotalTaxedPrice($country, $state);
    }

    /**
     * @throws PropelException
     */
    public function getTotalTaxedPrice(Country $country, ?State $state = null): float
    {
        return $this->lineTotal($this->getTaxedPrice($country, $state));
    }

    /**
     * @throws PropelException
     */
    public function getTotalTaxedPromoPrice(Country $country, ?State $state = null)
    {
        return $this->lineTotal($this->getTaxedPromoPrice($country, $state));
    }

    public function getTotalPrice(): float
    {
        return $this->lineTotal((float) $this->getPrice());
    }

    public function getTotalPromoPrice(): float
    {
        return $this->lineTotal((float) $this->getPromoPrice());
    }

    public function getTotalRealPrice(): float
    {
        return $this->lineTotal($this->getRealPrice());
    }

    /**
     * Turns a unit price into a line total, rounding where the shop's
     * order_rounding_mode says to round.
     *
     * Order::getTotalAmount() has to reach the same figure from the persisted
     * order lines, so any change here belongs there too.
     */
    private function lineTotal(float $unitPrice): float
    {
        if (ConfigQuery::isRoundingModeRoundingOfSums()) {
            return round($unitPrice * $this->getQuantity(), 2);
        }

        return round($unitPrice, 2) * $this->getQuantity();
    }
}
