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

use Thelia\Model\ProductAssociatedContent;
use Thelia\Core\Event\ActionEvent;

class ProductAssociatedContentEvent extends ActionEvent
{
    public $content = null;

    public function __construct(ProductAssociatedContent $content = null)
    {
        $this->content = $content;
    }

    public function hasProductAssociatedContent()
    {
        return ! is_null($this->content);
    }

    public function getProductAssociatedContent()
    {
        return $this->content;
    }

    public function setProductAssociatedContent(ProductAssociatedContent $content)
    {
        $this->content = $content;

        return $this;
    }
}
