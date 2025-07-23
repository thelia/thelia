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

use Thelia\Model\Base\MetaData as BaseMetaData;

class MetaData extends BaseMetaData
{
    public const CATEGORY_KEY = 'category';
    public const PRODUCT_KEY = 'product';
    public const PSE_KEY = 'pse';
    public const FEATURE_KEY = 'feature';
    public const FEATURE_AV_KEY = 'feature_av';
    public const ATTRIBUTE_KEY = 'attribute';
    public const ATTRIBUTE_AV_KEY = 'attribute_av';
    public const BRAND_KEY = 'brand_av';
    public const FOLDER_KEY = 'folder';
    public const CONTENT_KEY = 'content';
    public const ORDER_KEY = 'order';
    public const ORDER_PRODUCT_KEY = 'order_product';
    public const MODULE_KEY = 'module';
    public const CUSTOMER_KEY = 'customer';
    public const ADDRESS_KEY = 'address';
    public const CURRENCY_KEY = 'currency';
    public const COUNTRY_KEY = 'country';
    public const LANG_KEY = 'lang';

    public function getValue()
    {
        $data = parent::getValue();

        if (parent::getIsSerialized()) {
            $data = @unserialize($data);
        }

        return $data;
    }

    public function setValue($v)
    {
        $isSerialized = false;
        $data = $v;

        if (null !== $data && (\is_array($data) || \is_object($data))) {
            $data = serialize($data);
            $isSerialized = true;
        }

        parent::setIsSerialized($isSerialized);

        return parent::setValue($data);
    }
}
