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

namespace Thelia\Core\Event\Folder;

/**
 * Class FolderUpdateEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderUpdateEvent extends FolderCreateEvent
{
    /** @var int */
    protected $folder_id;

    protected $chapo;
    protected $description;
    protected $postscriptum;

    /**
     * @param int $folder_id
     */
    public function __construct($folder_id)
    {
        $this->folder_id = $folder_id;
    }

    /**
     * @param mixed $chapo
     * @return $this
     */
    public function setChapo($chapo)
    {
        $this->chapo = $chapo;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getChapo()
    {
        return $this->chapo;
    }

    /**
     * @param mixed $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $folder_id
     * @return $this
     */
    public function setFolderId($folder_id)
    {
        $this->folder_id = $folder_id;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFolderId()
    {
        return $this->folder_id;
    }

    /**
     * @param mixed $postscriptum
     * @return $this
     */
    public function setPostscriptum($postscriptum)
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPostscriptum()
    {
        return $this->postscriptum;
    }
}
