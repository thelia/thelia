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

namespace Thelia\Core\Event\Content;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Content;

/**
 * Class ContentEvent
 * @package Thelia\Core\Event\Content
 * @author manuel raynaud <manu@raynaud.io>
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
