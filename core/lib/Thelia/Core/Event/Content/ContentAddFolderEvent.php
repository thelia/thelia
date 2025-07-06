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

use Thelia\Model\Content;

/**
 * Class ContentAddFolderEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContentAddFolderEvent extends ContentEvent
{
    /**
     * @param int $folderId
     */
    public function __construct(Content $content, /**
     * @var int folder id
     */
        protected $folderId)
    {
        parent::__construct($content);
    }

    /**
     * @param int $folderId
     */
    public function setFolderId($folderId): void
    {
        $this->folderId = $folderId;
    }

    /**
     * @return int
     */
    public function getFolderId()
    {
        return $this->folderId;
    }
}
