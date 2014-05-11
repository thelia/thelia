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

class CategoryAddContentEvent extends CategoryEvent
{
    protected $content_id;

    public function __construct(Category $category, $content_id)
    {
        parent::__construct($category);

        $this->content_id = $content_id;
    }

    public function getContentId()
    {
        return $this->content_id;
    }

    public function setContentId($content_id)
    {
        $this->content_id = $content_id;
    }
}
