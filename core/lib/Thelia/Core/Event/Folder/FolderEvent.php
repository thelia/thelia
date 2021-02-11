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

namespace Thelia\Core\Event\Folder;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Folder;

/**
 * Class FolderEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
 * @deprecated since 2.4, please use \Thelia\Model\Event\FolderEvent
 */
class FolderEvent extends ActionEvent
{
    /**
     * @var \Thelia\Model\Folder
     */
    protected $folder;

    public function __construct(Folder $folder = null)
    {
        $this->folder = $folder;
    }

    /**
     */
    public function setFolder(Folder $folder)
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @return \Thelia\Model\Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * test if a folder object exists
     *
     * @return bool
     */
    public function hasFolder()
    {
        return null !== $this->folder;
    }
}
