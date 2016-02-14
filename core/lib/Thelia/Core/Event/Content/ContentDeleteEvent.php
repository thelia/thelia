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

/**
 * Class ContentDeleteEvent
 * @package Thelia\Core\Event\Content
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentDeleteEvent extends ContentEvent
{
    /** @var int */
    protected $content_id;

    /** @var int */
    protected $folder_id;

    /**
     * @param int $content_id
     */
    public function __construct($content_id)
    {
        $this->content_id = $content_id;
    }

    /**
     * @param mixed $content_id
     *
     * @return $this
     */
    public function setContentId($content_id)
    {
        $this->content_id = $content_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getContentId()
    {
        return $this->content_id;
    }

    public function setDefaultFolderId($folderid)
    {
        $this->folder_id = $folderid;
    }

    public function getDefaultFolderId()
    {
        return $this->folder_id;
    }
}
