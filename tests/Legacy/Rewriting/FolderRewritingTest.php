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

namespace Thelia\Tests\Rewriting;

use Thelia\Model\Folder;

/**
 * Class FolderRewritingTest
 * @package Thelia\Tests\Rewriting
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class FolderRewritingTest extends BaseRewritingObject
{
    /**
     * @return mixed an instance of Product, Folder, Content or Category Model
     */
    public function getObject()
    {
        return new Folder();
    }
}
