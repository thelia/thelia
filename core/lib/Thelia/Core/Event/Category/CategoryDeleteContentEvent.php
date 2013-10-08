<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Category;

use Thelia\Core\Event\Category\CategoryEvent;
use Thelia\Model\Category;

class CategoryDeleteContentEvent extends CategoryEvent
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
