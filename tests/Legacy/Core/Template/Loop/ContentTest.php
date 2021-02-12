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

use Thelia\Model\ContentQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ContentTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Content';
    }

    public function getMandatoryArguments()
    {
        return [];
    }

    public function testSearchById(): void
    {
        $content = ContentQuery::create()->findOne();
        if (null === $content) {
            $content = new \Thelia\Model\Content();
            $content->setVisible(1);
            $content->setTitle('foo');
            $content->save();
        }

        $otherParameters = [
            'visible' => '*',
        ];

        $this->baseTestSearchById($content->getId(), $otherParameters);
    }

    public function testSearchLimit(): void
    {
        $this->baseTestSearchWithLimit(3);
    }
}
