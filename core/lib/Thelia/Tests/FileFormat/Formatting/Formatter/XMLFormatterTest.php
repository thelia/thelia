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
use Thelia\Core\FileFormat\Formatting\Formatter\XMLFormatter;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;

/**
 * Class XMLFormatterTest
 * @package Thelia\Tests\FileFormat\Formatting\Formatter
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class XMLFormatterTest extends \PHPUnit_Framework_TestCase
{
    /** @var XMLFormatter */
    protected $formatter;

    public function setUp()
    {
        new Translator(new Container());

        $this->formatter = new XMLFormatter();
    }

    public function testMetaData()
    {
        $this->assertEquals(
            "XML",
            $this->formatter->getName()
        );

        $this->assertEquals(
            "xml",
            $this->formatter->getExtension()
        );

        $this->assertEquals(
            "application/xml",
            $this->formatter->getMimeType()
        );
    }

    public function testSimpleEncode()
    {
        $data = new FormatterData();

        $data->setData(
            [
                "foo" => "bar"
            ]
        );

        $dom = new \DOMDocument("1.0");
        $dom->appendChild(new \DOMElement("data"))
            ->appendChild(new \DOMElement("foo", "bar"));
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $this->assertEquals(
            $dom->saveXML(),
            $this->formatter->encode($data)
        );
    }

    public function testComplexEncode()
    {
        $data = new FormatterData();
        $this->formatter->nodeName = "baz";

        $data->setData(
            [
                "foo" => "bar",
                [
                    "name" => "banana",
                    "type" => "fruit",
                ],
                [
                    "orange"=>[
                        "type" => "fruit"
                    ],
                    "banana"=>[
                        "like" => "Yes"
                    ]
                ]
            ]
        );

        $dom = new \DOMDocument("1.0");
        $base = $dom->appendChild(new \DOMElement("data"));
        $base->appendChild(new \DOMElement("foo", "bar"));

        $baz = $base->appendChild(new \DOMElement("baz"));
        $baz->appendChild(new \DOMElement("name", "banana"));
        $baz->appendChild(new \DOMElement("type", "fruit"));

        $baz = $base->appendChild(new \DOMElement("baz"));
        $orange = $baz->appendChild(new \DOMElement("orange"));
        $orange->appendChild(new \DOMElement("type","fruit"));
        $banana = $baz->appendChild(new \DOMElement("banana"));
        $banana->appendChild(new \DOMElement("like", "Yes"));

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $this->assertEquals(
            $dom->saveXML(),
            $this->formatter->encode($data)
        );
    }

    public function testSimpleDecode()
    {
        $dom = new \DOMDocument("1.0");
        $dom->appendChild(new \DOMElement("data"))
            ->appendChild(new \DOMElement("foo", "bar"));
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $raw = $dom->saveXML();

        $data = $this->formatter->decode($raw);

        $this->assertEquals(["foo" => "bar"], $data->getData());
    }

    public function testComplexDecode()
    {
        $expectedData =
        [
            "foo" => "bar",
            [
                "name" => "banana",
                "type" => "fruit",
            ],
            [
                "orange"=>[
                    "type" => "fruit"
                ],
                "banana"=>[
                    "like" => "Yes"
                ]
            ]
        ];

        $dom = new \DOMDocument("1.0");
        $base = $dom->appendChild(new \DOMElement("data"));
        $base->appendChild(new \DOMElement("foo", "bar"));

        $baz = $base->appendChild(new \DOMElement("baz"));
        $baz->appendChild(new \DOMElement("name", "banana"));
        $baz->appendChild(new \DOMElement("type", "fruit"));

        $baz = $base->appendChild(new \DOMElement("baz"));
        $orange = $baz->appendChild(new \DOMElement("orange"));
        $orange->appendChild(new \DOMElement("type","fruit"));
        $banana = $baz->appendChild(new \DOMElement("banana"));
        $banana->appendChild(new \DOMElement("like", "Yes"));

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $this->formatter->nodeName = "baz";
        $data = $this->formatter->decode($dom->saveXML());

        $this->assertEquals($expectedData, $data->getData());
    }
}