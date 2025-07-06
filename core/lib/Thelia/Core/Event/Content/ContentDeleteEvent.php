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

namespace Thelia\Core\Event\Content;

/**
 * Class ContentDeleteEvent.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class ContentDeleteEvent extends ContentEvent
{
    /** @var int */
    protected $folder_id;

    /**
     * @param int $content_id
     */
    public function __construct(protected $content_id)
    {
    }

    /**
     * @return $this
     */
    public function setContentId($content_id): static
    {
        $this->content_id = $content_id;

        return $this;
    }

    public function getContentId()
    {
        return $this->content_id;
    }

    public function setDefaultFolderId($folderid): void
    {
        $this->folder_id = $folderid;
    }

    public function getDefaultFolderId()
    {
        return $this->folder_id;
    }
}
