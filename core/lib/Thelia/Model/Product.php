<?php

namespace Thelia\Model;

use Thelia\Model\Base\Product as BaseProduct;
use Thelia\Tools\URL;

class Product extends BaseProduct
{
    public function getUrl($locale)
    {
        return URL::init()->retrieve('product', $this->getId(), $locale)->toString();
    }
}
