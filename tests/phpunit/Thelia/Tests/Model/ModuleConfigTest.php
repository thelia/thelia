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

namespace Thelia\Tests\Model;

use Thelia\Model\Base\ModuleQuery;
use Thelia\Model\ModuleConfig;
use Thelia\Model\ModuleConfigQuery;

/**
 * Class ModuleConfigTest
 * @package Thelia\Tests\Model
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ModuleConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testGetWithDefault()
    {
        $moduleModel = ModuleQuery::create()->findOne();

        $moduleConfig = ModuleConfigQuery::create()->setConfigValue(
            $moduleModel->getId(),
            'test-name',
            'test-value'
        );

        $val = ModuleConfigQuery::create()->getConfigValue($moduleModel->getId(), 'do-not-exists', 'default-value');

        $this->assertEquals($val, 'default-value');

        $moduleConfig->deleteConfigValue($moduleModel->getId(), 'test-name');
    }

    public function testSetNoLocale()
    {
        $moduleModel = ModuleQuery::create()->findOne();

        $moduleConfig = ModuleConfigQuery::create()->setConfigValue(
            $moduleModel->getId(),
            'test-name-1',
            'test-value'
        );

        $val = $moduleConfig->getConfigValue($moduleModel->getId(), 'test-name-1');
        $this->assertEquals($val, 'test-value');

        $val1 = $moduleConfig->getConfigValue($moduleModel->getId(), 'test-name-1', 'default-value', 'fr_FR');
        $this->assertEquals($val1, 'default-value');

        $moduleConfig->deleteConfigValue($moduleModel->getId(), 'test-name-1');
    }

    public function testSetLocale()
    {
        $moduleModel = ModuleQuery::create()->findOne();

        $moduleConfig = ModuleConfigQuery::create()->setConfigValue(
            $moduleModel->getId(),
            'test-name-2',
            'test-value-uk',
            'en_UK'
        );

        $moduleConfig->setConfigValue(
            $moduleModel->getId(),
            'test-name-2',
            'test-value-fr',
            'fr_FR'
        );

        $val = $moduleConfig->getConfigValue($moduleModel->getId(), 'test-name-2', null, 'en_UK');
        $this->assertEquals($val, 'test-value-uk');

        $val1 = $moduleConfig->getConfigValue($moduleModel->getId(), 'test-name-2', null, 'fr_FR');
        $this->assertEquals($val1, 'test-value-fr');

        $val1 = $moduleConfig->getConfigValue($moduleModel->getId(), 'test-name-2', null, 'en_US');
        $this->assertNull($val1);

        $moduleConfig->deleteConfigValue($moduleModel->getId(), 'test-name-2');
    }

    public function testDeleteNotFound()
    {
        $moduleModel = ModuleQuery::create()->findOne();

        ModuleConfigQuery::create()->deleteConfigValue($moduleModel->getId(), 'do-not-exists');
    }

    public function testDelete()
    {
        $moduleModel = ModuleQuery::create()->findOne();

        $moduleConfig = ModuleConfigQuery::create()->setConfigValue(
            $moduleModel->getId(),
            'test-name-3',
            'test-value'
        );
        ModuleConfigQuery::create()->deleteConfigValue($moduleModel->getId(), 'test-name-3');

        $val1 = $moduleConfig->getConfigValue($moduleModel->getId(), 'test-name-3');
        $this->assertNull($val1);
    }

    /**
     * @expectedException \LogicException
     */
    public function testSetNotExists()
    {
        $moduleModel = ModuleQuery::create()->findOne();

        $moduleConfig = ModuleConfigQuery::create()->setConfigValue(
            $moduleModel->getId(),
            'test-name-4',
            'test-value',
            null,
            false
        );
    }
}
