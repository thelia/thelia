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

namespace Thelia\Domain\Promotion\Coupon\Type;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Exception\NotEnoughStockException;
use Thelia\Domain\Promotion\Coupon\FacadeInterface;
use Thelia\Domain\Shipping\DestinationRestriction\CartDestinations;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\DestinationRestriction\ProductRefusedByDestinationException;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Allow to remove an amount from the checkout total.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class FreeProduct extends AbstractRemoveOnProducts
{
    public const OFFERED_PRODUCT_ID = 'offered_product_id';
    public const OFFERED_CATEGORY_ID = 'offered_category_id';

    protected string $serviceId = 'thelia.coupon.type.free_product';
    protected ?int $offeredProductId = null;
    protected ?int $offeredCategoryId = null;

    /**
     * This constant is user to mark a free product as in the process of being added to the cart,
     * but the CartItem ID is not yet been defined.
     */
    public const ADD_TO_CART_IN_PROCESS = -1;

    private const SESSION_VAR_PREFIX = 'coupon.free_product.cart_items.';

    public function __construct(
        FacadeInterface $facade,
        protected ?DestinationRestrictionChecker $destinationRestrictionChecker = null,
    ) {
        parent::__construct($facade);
    }

    /**
     * The cart lines the free product promotions of this session hold as their gift.
     *
     * @return list<int>
     */
    public static function giftCartItemIds(SessionInterface $session): array
    {
        $cartItemIds = [];

        foreach ($session->all() as $name => $cartItemIdOfProduct) {
            if (!str_starts_with((string) $name, self::SESSION_VAR_PREFIX) || !\is_array($cartItemIdOfProduct)) {
                continue;
            }

            foreach ($cartItemIdOfProduct as $cartItemId) {
                if (\is_int($cartItemId) && $cartItemId > 0) {
                    $cartItemIds[] = $cartItemId;
                }
            }
        }

        return array_values(array_unique($cartItemIds));
    }

    public function setFieldsValue(array $effects): void
    {
        $this->offeredProductId = empty($effects[self::OFFERED_PRODUCT_ID])
            ? null
            : (int) $effects[self::OFFERED_PRODUCT_ID];
        $this->offeredCategoryId = empty($effects[self::OFFERED_CATEGORY_ID])
            ? null
            : (int) $effects[self::OFFERED_CATEGORY_ID];
    }

    public function getCartItemDiscount(CartItem $cartItem): float
    {
        // This method is not used, we use our own implementation of exec();
        return .0;
    }

    /**
     * @return string The session variable where the cart item IDs for the free products are stored
     */
    protected function getSessionVarName(): string
    {
        return self::SESSION_VAR_PREFIX.$this->getCode();
    }

    /**
     * Return the cart item id which contains the free product related to a given product.
     */
    protected function getRelatedCartItem(Product $product): CartItem|bool|int
    {
        $cartItemIdList = $this->facade->getRequest()->getSession()->get(
            $this->getSessionVarName(),
            [],
        );

        if (isset($cartItemIdList[$product->getId()])) {
            $cartItemId = $cartItemIdList[$product->getId()];

            if (self::ADD_TO_CART_IN_PROCESS === $cartItemId) {
                return self::ADD_TO_CART_IN_PROCESS;
            }

            if (null !== $cartItem = CartItemQuery::create()->findPk($cartItemId)) {
                return $cartItem;
            }
        } else {
            // Maybe the product we're offering is already in the cart ? Search it.
            $cartItems = $this->facade->getCart()->getCartItems();

            /** @var CartItem $cartItem */
            foreach ($cartItems as $cartItem) {
                if ($cartItem->getProduct()->getId() === $this->offeredProductId) {
                    // We found the product. Store its cart item as the free product container.
                    $this->setRelatedCartItem($product, $cartItem->getId());

                    return $cartItem;
                }
            }
        }

        return false;
    }

    /**
     * Set the cart item id which contains the free product related to a given product.
     */
    protected function setRelatedCartItem(Product $product, bool|int $cartItemId): void
    {
        $cartItemIdList = $this->facade->getRequest()->getSession()->get(
            $this->getSessionVarName(),
            [],
        );

        if (!\is_array($cartItemIdList)) {
            $cartItemIdList = [];
        }

        $cartItemIdList[$product->getId()] = $cartItemId;

        $this->facade->getRequest()->getSession()->set(
            $this->getSessionVarName(),
            $cartItemIdList,
        );
    }

    /**
     * Get the product id / cart item id list.
     */
    protected function getFreeProductsCartItemIds(): mixed
    {
        return $this->facade->getRequest()->getSession()->get(
            $this->getSessionVarName(),
            [],
        );
    }

    /**
     * Clear the session variable.
     */
    protected function clearFreeProductsCartItemIds(): mixed
    {
        return $this->facade->getRequest()->getSession()->remove($this->getSessionVarName());
    }

    /**
     * We overload this method here to remove the free products when the
     * coupons conditions are no longer met.
     *
     * {@inheritdoc}
     */
    public function isMatching(): bool
    {
        $match = parent::isMatching();

        if (!$match) {
            // Cancel coupon effect (but no not remove the product)
            $this->clearFreeProductsCartItemIds();
        }

        return $match;
    }

    public function exec(): float
    {
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var Product $eligibleProduct */
        $eligibleProduct = null;

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (\in_array($cartItem->getProduct()->getId(), $this->product_list, true) && (!$cartItem->getPromo() || $this->isAvailableOnSpecialOffers())) {
                // The product, not the cart line: getRelatedCartItem() and
                // setRelatedCartItem() key the session marker on the product id.
                $eligibleProduct = $cartItem->getProduct();
                break;
            }
        }

        if (null !== $eligibleProduct) {
            // Get the cart item for the eligible product
            $freeProductCartItem = $this->getRelatedCartItem($eligibleProduct);

            // We add the free product it only if it not yet in the cart.
            if (false === $freeProductCartItem
                && null !== $this->offeredProductId
                && null !== $freeProduct = ProductQuery::create()->findPk($this->offeredProductId)) {
                // Store in the session that the free product is added to the cart,
                // so that we don't enter the following infinite loop :
                //
                // 1) exec() adds a product by firing a CART_ADDITEM event,
                // 2) the event is processed by Action\Coupon::updateOrderDiscount(),
                // 3) Action\Coupon::updateOrderDiscount() calls CouponManager::getDiscount()
                // 4) CouponManager::getDiscount() calls exec() -> Infinite loop !!
                // Store a marker first, we do not have the cart item id yet.
                $this->setRelatedCartItem($eligibleProduct, self::ADD_TO_CART_IN_PROCESS);
                $cartEvent = new CartEvent($this->facade->getCart());
                $cartEvent->setNewness(true);
                $cartEvent->setAppend(false);
                $cartEvent->setQuantity(1);
                $cartEvent->setProductSaleElementsId($freeProduct->getDefaultSaleElements()->getId());
                $cartEvent->setProductId($this->offeredProductId);

                try {
                    $this->facade->getDispatcher()->dispatch($cartEvent, TheliaEvents::CART_ADDITEM);
                } catch (NotEnoughStockException|ProductRefusedByDestinationException) {
                    // A gift the shop cannot ship (out of stock, or refused by the
                    // destination of the cart) is not offered, and the customer's own
                    // addition goes through. The marker is dropped so the gift is tried
                    // again once it is back in stock or the destination changes.
                    $this->clearFreeProductsCartItemIds();

                    return 0.0;
                }

                // Store the final cart item ID.
                $this->setRelatedCartItem($eligibleProduct, $cartEvent->getCartItem()->getId());
                $freeProductCartItem = $cartEvent->getCartItem();
            }

            if ($freeProductCartItem instanceof CartItem && $this->theDestinationRefuses($freeProductCartItem)) {
                // The cart now goes where the gift cannot travel (the buyer chose another
                // delivery address): the gift is withdrawn rather than blocking the order.
                $freeProductCartItem->delete();
                $this->clearFreeProductsCartItemIds();
                $this->facade->getCart()?->clearCartItems();

                return 0.0;
            }

            if ($freeProductCartItem instanceof CartItem) {
                // The discount is the product price.
                $discount = $freeProductCartItem->getRealTaxedPrice($this->facade->getDeliveryCountry());
            }

        // No eligible product was found !
        } else {
            // Remove all free products for this coupon, but no not remove the product from the cart.
            $this->clearFreeProductsCartItemIds();
        }

        return (float) $discount;
    }

    /**
     * Judged the way the addition to the cart judges a product: on the addresses of the
     * cart, or the default address of the customer before one is chosen.
     */
    private function theDestinationRefuses(CartItem $giftCartItem): bool
    {
        $productId = (int) $giftCartItem->getProductId();

        if (null === $this->destinationRestrictionChecker || !$this->destinationRestrictionChecker->isRestricted($productId)) {
            return false;
        }

        $cart = $giftCartItem->getCart();

        if (!$cart instanceof Cart) {
            return false;
        }

        return [] !== $this->destinationRestrictionChecker->violationsForProducts(
            [$productId],
            CartDestinations::delivery($cart, true),
            CartDestinations::billing($cart, true),
            Translator::getInstance()->getLocale() ?: Lang::getDefaultLanguage()->getLocale(),
        );
    }

    protected function getFieldList(): array
    {
        return $this->getBaseFieldList([self::OFFERED_CATEGORY_ID, self::OFFERED_PRODUCT_ID]);
    }

    protected function checkCouponFieldValue(string $fieldName, string $fieldValue): string
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if (self::OFFERED_PRODUCT_ID === $fieldName) {
            if ((float) $fieldValue < 0) {
                throw new \InvalidArgumentException(Translator::getInstance()->trans('Please select the offered product'));
            }
        } elseif (self::OFFERED_CATEGORY_ID === $fieldName) {
            if ('' === $fieldValue || '0' === $fieldValue) {
                throw new \InvalidArgumentException(Translator::getInstance()->trans('Please select the category of the offred product'));
            }
        }

        return $fieldValue;
    }

    /**
     * Get I18n name.
     */
    public function getName(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans('Free product when buying one or more selected products', []);
    }

    public function getToolTip(): string
    {
        return $this->facade
            ->getTranslator()
            ->trans(
                'This coupon adds a free product to the cart if one of the selected products is in the cart.',
                [],
            );
    }

    public function drawBackOfficeInputs(): string
    {
        return $this->drawBaseBackOfficeInputs('coupon/type-fragments/free-product.html', [
            'offered_category_field_name' => $this->makeCouponFieldName(self::OFFERED_CATEGORY_ID),
            'offered_category_value' => $this->offeredCategoryId,

            'offered_product_field_name' => $this->makeCouponFieldName(self::OFFERED_PRODUCT_ID),
            'offered_product_value' => $this->offeredProductId,
        ]);
    }

    public function clear(): void
    {
        // Clear the session variable when the coupon is cleared.
        $this->clearFreeProductsCartItemIds();
    }
}
