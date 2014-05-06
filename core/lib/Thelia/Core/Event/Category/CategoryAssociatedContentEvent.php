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

namespace Thelia\Core\Event\Category;

use Thelia\Model\CategoryAssociatedContent;
use Thelia\Core\Event\ActionEvent;

class CategoryAssociatedContentEvent extends ActionEvent
{
    public $content = null;

    public function __construct(CategoryAssociatedContent $content = null)
    {
        $this->content = $content;
    }

    public function hasCategoryAssociatedContent()
    {
        return ! is_null($this->content);
    }

    public function getCategoryAssociatedContent()
    {
        return $this->content;
    }

    public function setCategoryAssociatedContent(CategoryAssociatedContent $content)
    {
        $this->content = $content;

        return $this;
    }
}
