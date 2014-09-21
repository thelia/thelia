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

namespace Thelia\Tests\FileFormat\Formatting\Formatter;

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\FileFormat\Formatting\Formatter\CSVFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;

/**
 * Class CSVFormatterTest
 * @package Thelia\Tests\FileFormat\Formatting\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CSVFormatterTest extends \PHPUnit_Framework_TestCase
{
    /** @var  CSVFormatter */
    protected $formatter;

    public function setUp()
    {
        new Translator(new Container());

        $this->formatter = new CSVFormatter();
    }

    public function testSimpleEncode()
    {
        $expected = "\"ref\";\"stock\"\n\"foo\";\"bar\"";

        $data = [
            [
                "ref" => "foo",
                "stock" => "bar",
            ],
        ];

        $data = (new FormatterData())->setData($data);

        $this->assertEquals(
            $expected,
            $this->formatter->encode($data)
        );
    }

    public function testComplexEncode()
    {
        $this->formatter->lineReturn = "\n";
        $this->formatter->delimiter = ",";
        $expected = "\"foo\",\"bar\",\"baz\"\n\"1\",\"2\",\"3\"\n\"4\",\"5\",\"6\"\n\"1\",\"2\",\"3\"";

        $data = [
            [
                "foo" => "1",
                "bar" => "2",
                "baz" => "3",
            ],
            [
                "foo" => "4",
                "bar" => "5",
                "baz" => "6",
            ],
            [
                "foo" => "1",
                "bar" => "2",
                "baz" => "3",
            ],
        ];

        $data = (new FormatterData())->setData($data);

        $this->assertEquals(
            $expected,
            $this->formatter->encode($data)
        );
    }

    public function testSimpleDecode()
    {
        $data = "\"ref\";\"stock\"\n\"foo\";\"bar\"";

        $expected = [
            [
                "ref" => "foo",
                "stock" => "bar",
            ],
        ];

        $this->assertEquals(
            $expected,
            $this->formatter->decode($data)->getData()
        );
    }

    public function testComplexDecode()
    {
        $this->formatter->lineReturn = "\n";
        $this->formatter->delimiter = ",";
        $data = "\"foo\",\"bar\",baz\n\"1\",\"2\",3\n\"4\",5,\"6\"\n\"1\",\"2\",\"3\"";

        $expected = [
            [
                "foo" => "1",
                "bar" => "2",
                "baz" => "3",
            ],
            [
                "foo" => "4",
                "bar" => "5",
                "baz" => "6",
            ],
            [
                "foo" => "1",
                "bar" => "2",
                "baz" => "3",
            ],
        ];

        $this->assertEquals(
            $expected,
            $this->formatter->decode($data)->getData()
        );
    }
}
