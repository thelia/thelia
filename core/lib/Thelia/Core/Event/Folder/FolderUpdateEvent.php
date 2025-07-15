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
 * Class FolderUpdateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderUpdateEvent extends FolderCreateEvent
{
    protected $chapo;

    protected $description;

    protected $postscriptum;

    public function __construct(protected int $folder_id)
    {
    }

    public function setChapo(?string $chapo): self
    {
        $this->chapo = $chapo;

        return $this;
    }

    public function getChapo()
    {
        return $this->chapo;
    }

    public function setDescription($description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setFolderId(int $folder_id): self
    {
        $this->folder_id = $folder_id;

        return $this;
    }

    public function getFolderId(): int
    {
        return $this->folder_id;
    }

    public function setPostscriptum(?string $postscriptum): self
    {
        $this->postscriptum = $postscriptum;

        return $this;
    }

    public function getPostscriptum()
    {
        return $this->postscriptum;
    }
}
