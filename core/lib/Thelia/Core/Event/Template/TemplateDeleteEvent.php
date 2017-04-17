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

namespace Thelia\Core\Event\Template;

class TemplateDeleteEvent extends TemplateEvent
{
    /** @var int */
    protected $template_id;

    protected $product_count;

    /**
     * @param int $template_id
     */
    public function __construct($template_id)
    {
        $this->setTemplateId($template_id);
    }

    public function getTemplateId()
    {
        return $this->template_id;
    }

    public function setTemplateId($template_id)
    {
        $this->template_id = $template_id;

        return $this;
    }

    public function getProductCount()
    {
        return $this->product_count;
    }

    public function setProductCount($product_count)
    {
        $this->product_count = $product_count;

        return $this;
    }
}
