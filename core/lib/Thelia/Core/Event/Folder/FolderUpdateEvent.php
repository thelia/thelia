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
 * @author Manuel Raynaud <manu@thelia.net>
 */
class FolderUpdateEvent extends FolderCreateEvent
{
    protected $folder_id;

    protected $chapo;
    protected $description;
    protected $postscriptum;

    public function __construct($folder_id)
    {
        $this->folder_id = $folder_id;
    }

    /**
     * @param mixed $chapo
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
