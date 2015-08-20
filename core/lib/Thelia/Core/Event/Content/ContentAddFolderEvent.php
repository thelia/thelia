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

use Thelia\Model\Content;

/**
 * Class ContentAddFolderEvent
 * @package Thelia\Core\Event\Content
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ContentAddFolderEvent extends ContentEvent
{
    /**
     * @var int folder id
     */
    protected $folderId;

    public function __construct(Content $content, $folderId)
    {
        $this->folderId = $folderId;

        parent::__construct($content);
    }

    /**
     * @param int $folderId
     */
    public function setFolderId($folderId)
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
