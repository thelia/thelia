<?php

declare(strict_types=1);

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

/**
 * Class FolderDeleteEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderDeleteEvent extends FolderEvent
{
    /**
     * @param int $folder_id
     */
    public function __construct(protected $folder_id)
    {
    }

    /**
     * @param int $folder_id
     */
    public function setFolderId($folder_id): void
    {
        $this->folder_id = $folder_id;
    }

    /**
     * @return int
     */
    public function getFolderId()
    {
        return $this->folder_id;
    }
}
