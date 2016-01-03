<?php

namespace Thelia\Model;

use Thelia\Model\Base\MetaData as BaseMetaData;

class MetaData extends BaseMetaData
{
    const CATEGORY_KEY     = 'category';
    const PRODUCT_KEY      = 'product';
    const PSE_KEY          = 'pse';
    const FEATURE_KEY      = 'feature';
    const FEATURE_AV_KEY   = 'feature_av';
    const ATTRIBUTE_KEY    = 'attribute';
    const ATTRIBUTE_AV_KEY = 'attribute_av';
    const BRAND_KEY        = 'brand_av';

    const FOLDER_KEY  = 'folder';
    const CONTENT_KEY = 'content';

    const ORDER_KEY = 'order';
    const ORDER_PRODUCT_KEY = 'order_product';

    const MODULE_KEY = 'module';

    const CUSTOMER_KEY = 'customer';
    const ADDRESS_KEY  = 'address';

    const CURRENCY_KEY = 'currency';
    const COUNTRY_KEY  = 'country';
    const LANG_KEY     = 'lang';

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
        $data         = $v;
        if (null !== $data) {
            if (is_array($data) || is_object($data)) {
                $data         = serialize($data);
                $isSerialized = true;
            }
        }

        parent::setIsSerialized($isSerialized);

        return parent::setValue($data);
    }
}
