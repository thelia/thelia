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

use Thelia\Core\Translation\Translator;
use Thelia\Model\CartItem;

class OfferedProduct extends AbstractRemove
{
    const OFFERED_PRODUCT_ID  = 'offered_product_id';
    const OFFERED_CATEGORY_ID = 'offered_category_id';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.offered_product';

    protected $offeredProductId;
    protected $offeredCategoryId;

    protected $category_list = array();

    protected function getSessionVarName()
    {
        return "coupon.offered_product.cart_items." . $this->getCode();
    }

    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Offer a product', array());
    }

    public function getToolTip()
    {
        return '';
    }

    public function getCartItemDiscount(CartItem $cartItem)
    {
        if ($cartItem->getProductId() == $this->offeredProductId) {
            return  $cartItem->getRealTaxedPrice($this->facade->getDeliveryCountry());
        }

        return 0;
    }

    public function setFieldsValue($effects)
    {
        $this->offeredProductId = $effects[self::OFFERED_PRODUCT_ID];
        $this->offeredCategoryId = $effects[self::OFFERED_CATEGORY_ID];
        $this->category_list[] = $effects[self::OFFERED_CATEGORY_ID];
    }

    public function drawBackOfficeInputs()
    {
        return $this->drawBaseBackOfficeInputs("coupon/type-fragments/offered-product.html", [
            'offered_category_field_name' => $this->makeCouponFieldName(self::OFFERED_CATEGORY_ID),
            'offered_category_value'      => $this->offeredCategoryId,

            'offered_product_field_name'  => $this->makeCouponFieldName(self::OFFERED_PRODUCT_ID),
            'offered_product_value'       => $this->offeredProductId
        ]);
    }

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
                        'Please select the category of the offered product'
                    )
                );
            }
        }

        return $fieldValue;
    }

    protected function getFieldList()
    {
        return  $this->getBaseFieldList([self::OFFERED_CATEGORY_ID, self::OFFERED_PRODUCT_ID]);
    }
}