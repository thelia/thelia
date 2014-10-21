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

namespace Thelia\Tests\Module\Validator;

use Thelia\Module\Validator\ModuleValidator;

/**
 * Class ModuleValidator
 * @package Thelia\Tests\Module\Validator
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException \Thelia\Exception\ModuleException
     */
    public function testCheque()
    {
        $moduleChequePath = THELIA_MODULE_DIR . "Cheque";

        $moduleValidator = new ModuleValidator($moduleChequePath);

        $moduleValidator->setTranslator($this->getStubTranslator());

        // load module
        $moduleValidator->load();

        $moduleDescriptor = $moduleValidator->getModuleDescriptor();

        $this->assertInstanceOf('SimpleXMLElement', $moduleDescriptor);
        $this->assertEquals("1", $moduleValidator->getModuleVersion());

        $moduleDefinition = $moduleValidator->getModuleDefinition();

        $this->assertInstanceOf('Thelia\Module\Validator\ModuleDefinition', $moduleDefinition);

        $this->assertEquals("Cheque", $moduleDefinition->getCode());
        $this->assertEquals("Cheque\\Cheque", $moduleDefinition->getNamespace());

        // validate
        $moduleValidator->validate();
    }


    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     */
    public function testNonExistentModule()
    {
        $moduleChuckNorrisPath = THELIA_MODULE_DIR . "ChuckNorris";

        $moduleValidator = new ModuleValidator($moduleChuckNorrisPath);

        $moduleValidator->setTranslator($this->getStubTranslator());

        // load
        $moduleValidator->load();
    }


    private function getStubTranslator($i18nOutput = '')
    {
        $stubTranslator = $this->getMockBuilder('\Thelia\Core\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $stubTranslator->expects($this->any())
            ->method('trans')
            ->will($this->returnValue($i18nOutput));

        return $stubTranslator;
    }
}
