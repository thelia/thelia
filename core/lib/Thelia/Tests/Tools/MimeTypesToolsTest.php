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

namespace Thelia\Tests\Tools;
use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\MimeTypeTools;

/**
 * Class MimeTypesToolsTest
 * @package Thelia\Tests\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class MimeTypesToolsTest extends \PHPUnit_Framework_TestCase
{
    /** @var MimeTypeTools */
    protected $tool;

    public function setUp()
    {
        new Translator(new Container());

        $this->tool = MimeTypeTools::getInstance();
    }

    public function testTrim()
    {
        $this->assertEquals(
            "foo",
            $this->tool->realTrim(" foo ")
        );

        $this->assertEquals(
            "foo bar",
            $this->tool->realTrim(" foo  bar  ")
        );

        $this->assertEquals(
            "foo bar",
            $this->tool->realTrim(" foo  bar  ")
        );

        $this->assertEquals(
            "# foO/x-bar",
            $this->tool->realTrim(" # foO/x-bar  ")
        );

        $this->assertEquals(
            "foO/x-bar bar baz",
            $this->tool->realTrim("   foO/x-bar \t\t bar  baz ")
        );
    }

    public function testParseFile()
    {
        $array = $this->tool->parseFile();

        /**
         * check the format
         */

        foreach ($array as $key => $value) {
            $this->assertTrue(is_string($key));

            $this->assertTrue(is_array($value));

            foreach ($value as $entry) {
                $this->assertTrue(is_string($entry));
            }
        }
    }

    /**
     * @expectedException \Thelia\Exception\FileException
     */
    public function testParseFileFail()
    {
        $this->tool->parseFile("foo.bar");
    }

    public function testValidation()
    {
        $this->assertEquals(
            MimeTypeTools::TYPE_MATCH,
            $this->tool->validateMimeTypeExtension(
            "image/png", "foo.png"
        ));

        $this->assertEquals(
            MimeTypeTools::TYPE_MATCH,
            $this->tool->validateMimeTypeExtension(
            "image/png", "foo.PNg"
        ));

        $this->assertEquals(
            MimeTypeTools::TYPE_NOT_MATCH,
            $this->tool->validateMimeTypeExtension(
            "image/png", "foo.jpeg"
        ));

        $this->assertEquals(
            MimeTypeTools::TYPE_UNKNOWN,
            $this->tool->validateMimeTypeExtension(
            "thismimetype/doesntexists", "foo.bar"
        ));

        $this->assertEquals(
            MimeTypeTools::TYPE_UNKNOWN,
            $this->tool->validateMimeTypeExtension(
                "text/x-php", "foo.php"
            ));
    }
}
