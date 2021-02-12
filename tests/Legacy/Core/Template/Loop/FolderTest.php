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

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Model\FolderQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FolderTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Folder';
    }

    public function getMandatoryArguments()
    {
        return [];
    }

    public function testSearchById(): void
    {
        $folder = FolderQuery::create()->findOne();
        if (null === $folder) {
            $folder = new \Thelia\Model\Folder();
            $folder->setParent(0);
            $folder->setVisible(1);
            $folder->setTitle('foo');
            $folder->save();
        }

        $otherParameters = [
            'visible' => '*',
        ];

        $this->baseTestSearchById($folder->getId(), $otherParameters);
    }

    public function testSearchLimit(): void
    {
        $this->baseTestSearchWithLimit(3);
    }
}
