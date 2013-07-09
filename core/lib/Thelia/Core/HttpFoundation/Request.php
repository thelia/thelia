<?php
/**
 * Created by JetBrains PhpStorm.
 * User: manu
 * Date: 08/07/13
 * Time: 11:41
 * To change this template use File | Settings | File Templates.
 */

namespace Thelia\Core\HttpFoundation;

use Symfony\Component\HttpFoundation\Request as BaseRequest;


class Request extends BaseRequest{

    public function getProductId()
    {
        return $this->get("product_id");
    }

}