<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Coupon\Type;

use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * Allow to remove an amount from the checkout total
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class FreeProduct extends AbstractRemoveOnProducts
{
    const OFFERED_PRODUCT_ID  = 'offered_product_id';
    const OFFERED_CATEGORY_ID = 'offered_category_id';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.free_product';

    protected $offeredProductId;
    protected $offeredCategoryId;

    /**
     * This constant is user to mark a free product as in the process of being added to the cart,
     * but the CartItem ID is not yet been defined.
     */
    const ADD_TO_CART_IN_PROCESS = -1;

    /**
     * @inheritdoc
     */
    public function setFieldsValue($effects)
    {
        $this->offeredProductId = $effects[self::OFFERED_PRODUCT_ID];
        $this->offeredCategoryId = $effects[self::OFFERED_CATEGORY_ID];
    }

    /**
     * @inheritdoc
     */
    public function getCartItemDiscount(CartItem $cartItem)
    {
        // This method is not used, we use our own implementation of exec();
        return 0;
    }

    /**
     * @return string The session variable where the cart item IDs for the free products are stored
     */
    protected function getSessionVarName()
    {
        return "coupon.free_product.cart_items." . $this->getCode();
    }
    /**
     * Return the cart item id which contains the free product related to a given product
     *
     * @param Product $product the product in the cart which triggered the discount
     *
     * @return bool|int|CartItem the cart item which contains the free product, or false if the product is no longer in the cart, or ADD_TO_CART_IN_PROCESS if the adding process is not finished
     */
    protected function getRelatedCartItem($product)
    {
        $cartItemIdList =  $this->facade->getRequest()->getSession()->get(
            $this->getSessionVarName(),
            array()
        );

        if (isset($cartItemIdList[$product->getId()])) {
            $cartItemId = $cartItemIdList[$product->getId()];

            if ($cartItemId == self::ADD_TO_CART_IN_PROCESS) {
                return self::ADD_TO_CART_IN_PROCESS;
            } elseif (null !== $cartItem = CartItemQuery::create()->findPk($cartItemId)) {
                return $cartItem;
            }
        } else {
            // Maybe the product we're offering is already in the cart ? Search it.
            $cartItems = $this->facade->getCart()->getCartItems();

            /** @var CartItem $cartItem */
            foreach ($cartItems as $cartItem) {
                if ($cartItem->getProduct()->getId() == $this->offeredProductId) {
                    // We found the product. Store its cart item as the free product container.
                    $this->setRelatedCartItem($product, $cartItem->getId());

                    return $cartItem;
                }
            }
        }

        return false;
    }

    /**
     * Set the cart item id which contains the free product related to a given product
     *
     * @param Product  $product    the product in the cart which triggered the discount
     * @param bool|int $cartItemId the cart item ID which contains the free product, or just true if the free product is not yet added.
     */
    protected function setRelatedCartItem($product, $cartItemId)
    {
        $cartItemIdList = $this->facade->getRequest()->getSession()->get(
            $this->getSessionVarName(),
            array()
        );

        if (! is_array($cartItemIdList)) {
            $cartItemIdList = array();
        }

        $cartItemIdList[$product->getId()] = $cartItemId;

        $this->facade->getRequest()->getSession()->set(
            $this->getSessionVarName(),
            $cartItemIdList
        );
    }

    /**
     * Get the product id / cart item id list.
     *
     * @return array an array where the free product ID is the key, and the related cart item id the value.
     */
    protected function getFreeProductsCartItemIds()
    {
        return $this->facade->getRequest()->getSession()->get(
            $this->getSessionVarName(),
            array()
        );
    }

    /**
     * Clear the session variable.
     */
    protected function clearFreeProductsCartItemIds()
    {
        return $this->facade->getRequest()->getSession()->remove($this->getSessionVarName());
    }

    /**
     * We overload this method here to remove the free products when the
     * coupons conditions are no longer met.
     *
     * @inheritdoc
     */
    public function isMatching()
    {
        $match = parent::isMatching();

        if (! $match) {
            // Cancel coupon effect (but no not remove the product)
            $this->clearFreeProductsCartItemIds();
        }

        return $match;
    }

    /**
     * @inheritdoc
     */
    public function exec()
    {
        $discount = 0;

        $cartItems = $this->facade->getCart()->getCartItems();

        /** @var Product $eligibleProduct */
        $eligibleProduct = null;

        /** @var CartItem $cartItem */
        foreach ($cartItems as $cartItem) {
            if (in_array($cartItem->getProduct()->getId(), $this->product_list)) {
                if (! $cartItem->getPromo() || $this->isAvailableOnSpecialOffers()) {
                    $eligibleProduct = $cartItem;
                    break;
                }
            }
        }

        if ($eligibleProduct !== null) {
            // Get the cart item for the eligible product
            $freeProductCartItem = $this->getRelatedCartItem($eligibleProduct);

            // We add the free product it only if it not yet in the cart.
            if ($freeProductCartItem === false) {
                if (null !== $freeProduct = ProductQuery::create()->findPk($this->offeredProductId)) {
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
                    $cartEvent->setProduct($this->offeredProductId);

                    $this->facade->getDispatcher()->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);

                    // Store the final cart item ID.
                    $this->setRelatedCartItem($eligibleProduct, $cartEvent->getCartItem()->getId());

                    $freeProductCartItem = $cartEvent->getCartItem();
                }
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

        return $discount;
    }

    /**
     * @inheritdoc
     */
    protected function getFieldList()
    {
        return  $this->getBaseFieldList([self::OFFERED_CATEGORY_ID, self::OFFERED_PRODUCT_ID]);
    }

    /**
     * @inheritdoc
     */
    protected function checkCouponFieldValue($fieldName, $fieldValue)
    {
        $this->checkBaseCouponFieldValue($fieldName, $fieldValue);

        if ($fieldName === self::OFFERED_PRODUCT_ID) {
            if (floatval($fieldValue) < 0) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select the offered product'
                    )
                );
            }
        } elseif ($fieldName === self::OFFERED_CATEGORY_ID) {
            if (empty($fieldValue)) {
                throw new \InvalidArgumentException(
                    Translator::getInstance()->trans(
                        'Please select the category of the offred product'
                    )
                );
            }
        }

        return $fieldValue;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Free product when buying one or more selected products', array());
    }

    /**
     * @inheritdoc
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This coupon adds a free product to the cart if one of the selected products is in the cart.',
                array()
            );

        return $toolTip;
    }

    /**
     * @inheritdoc
     */
    public function drawBackOfficeInputs()
    {
        return $this->drawBaseBackOfficeInputs("coupon/type-fragments/free-product.html", [
                'offered_category_field_name' => $this->makeCouponFieldName(self::OFFERED_CATEGORY_ID),
                'offered_category_value'      => $this->offeredCategoryId,

                'offered_product_field_name'  => $this->makeCouponFieldName(self::OFFERED_PRODUCT_ID),
                'offered_product_value'       => $this->offeredProductId
            ]);
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        // Clear the session variable when the coupon is cleared.
        $this->clearFreeProductsCartItemIds();
    }
}
