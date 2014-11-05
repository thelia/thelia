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

namespace Thelia\Tests\Core\Template\Loop;

use Cheque\Cheque;
use Thelia\Core\Template\Loop\ModuleConfig;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 */
class ModuleConfigTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\ModuleConfig';
    }

    public function getTestedInstance()
    {
        return new ModuleConfig($this->container);
    }

    public function getMandatoryArguments()
    {
        return [
            "module" => "cheque",
            "variable" => "test"
        ];
    }

    public function testGetVariable()
    {
        Cheque::setConfigValue('test', 'test-value', null, true);

        $this->instance->initializeArgs([
                "type" => "module-config",
                "name" => "testGetVariable",
                "module" => "cheque",
                "variable" => "test"
            ]);

        $dummy = null;

        $loopResults = $this->instance->exec($dummy);

        $this->assertEquals(1, $loopResults->getCount());

        $substitutions = $loopResults->current()->getVarVal();

        $this->assertEquals('test-value', $substitutions['VALUE']);
    }

    public function testGetVariableWithDefault()
    {
        $this->instance->initializeArgs([
                "type" => "module-config",
                "name" => "testGetVariable",
                "module" => "cheque",
                "variable" => "nonexistent",
                "default_value" => "a default value"
            ]);

        $dummy = null;

        $loopResults = $this->instance->exec($dummy);

        $this->assertEquals(1, $loopResults->getCount());

        $substitutions = $loopResults->current()->getVarVal();

        $this->assertEquals('a default value', $substitutions['VALUE']);
    }

    public function testGetI18nVariable()
    {
        Cheque::setConfigValue('testI18N', 'test-value-i18n', 'fr_FR', true);

        $this->instance->initializeArgs(
            array(
                "type" => "foo",
                "name" => "foo",
                "module" => "cheque",
                "variable" => "testI18N",
                "locale" => "fr_FR"
            )
        )
        ;

        $dummy = null;

        $loopResults = $this->instance->exec($dummy);

        $this->assertEquals(1, $loopResults->getCount());

        $substitutions = $loopResults->current()->getVarVal();

        $this->assertEquals('test-value-i18n', $substitutions['VALUE']);
    }

    /**
     * @expectedException \LogicException
     */
    public function testNonExistentModule()
    {
        $this->instance->initializeArgs(
            array(
                "type" => "foo",
                "name" => "foo",
                "module" => "tagapouet",
                "variable" => "xdes"
            )
        )
        ;

        $dummy = null;

        $loopResults = $this->instance->exec($dummy);
    }
}
