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

namespace Thelia\Core\Event\Content;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Content;

/**
 * Class ContentEvent
 * @package Thelia\Core\Event\Content
 * @author manuel raynaud <mraynaud@openstudio.fr>
 */
class ContentEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Content
     */
    protected $content;

    public function __construct(Content $content = null)
    {
        $this->content = $content;
    }

    /**
     * @param \Thelia\Model\Content $content
     */
    public function setContent(Content $content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return \Thelia\Model\Content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * check if content exists
     *
     * @return bool
     */
    public function hasContent()
    {
        return null !== $this->content;
    }
}
