<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Category;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Category;

/**
 * @deprecated since 2.4, please use \Thelia\Model\Event\CategoryEvent
 */
class CategoryEvent extends ActionEvent
{
    public $category;

    public function __construct(Category $category = null)
    {
        $this->category = $category;
    }

    public function hasCategory()
    {
        return !\is_null($this->category);
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
