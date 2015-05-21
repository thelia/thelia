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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;

class ProductCloneEvent extends ActionEvent
{
    protected $ref;
    protected $productId;
    protected $lang;
    protected $cpId;

    public function __construct(
        $ref,
        $productId,
        $lang
    ) {
        $this->ref = $ref;
        $this->productId = $productId;
        $this->lang = $lang;
    }

    /**
     * @return mixed
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * @param mixed $ref
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    }

    /**
     * @return mixed
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * @param mixed $productId
     */
    public function setProductId($productId)
    {
        $this->productId = $productId;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    /**
     * @return mixed
     */
    public function getCpId()
    {
        return $this->cpId;
    }

    /**
     * @param mixed $cpId
     */
    public function setCpId($cpId)
    {
        $this->cpId = $cpId;
    }
}
