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

use Thelia\Model\Category;
use Thelia\Core\Event\ActionEvent;

class CategoryEvent extends ActionEvent
{
    public $category = null;

    public function __construct(Category $category = null)
    {
        $this->category = $category;
    }

    public function hasCategory()
    {
        return ! is_null($this->category);
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function setCategory(Category $category)
    {
        $this->category = $category;

        return $this;
    }
}
