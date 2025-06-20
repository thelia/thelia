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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Folder;

/**
 * Class FolderEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\FolderEvent
 */
class FolderEvent extends ActionEvent
{
    public function __construct(protected ?Folder $folder = null)
    {
    }

    public function setFolder(Folder $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder(): ?Folder
    {
        return $this->folder;
    }

    /**
     * test if a folder object exists.
     */
    public function hasFolder(): bool
    {
        return $this->folder instanceof Folder;
    }
}
