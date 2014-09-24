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

        /** @var \DOMElement $node */
        $node =$dom->appendChild(new \DOMElement($this->formatter->root))
            ->appendChild(new \DOMElement($this->formatter->nodeName));

        $node->setAttribute("name", "foo");
        $node->setAttribute("value", "bar");

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
        $base = $dom->appendChild(new \DOMElement($this->formatter->root));
        /** @var \DOMElement $node */
        $node = $base->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "foo");
        $node->setAttribute("value", "bar");

        $baz = $base->appendChild(new \DOMElement($this->formatter->rowName));
        $node = $baz->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "name");
        $node->setAttribute("value", "banana");
        $node = $baz->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "type");
        $node->setAttribute("value", "fruit");

        $baz = $base->appendChild(new \DOMElement($this->formatter->rowName));
        $orange = $baz->appendChild(new \DOMElement("orange"));
        $node = $orange->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "type");
        $node->setAttribute("value", "fruit");
        $banana = $baz->appendChild(new \DOMElement("banana"));
        $node = $banana->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "like");
        $node->setAttribute("value", "Yes");

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
        $node =$dom->appendChild(new \DOMElement($this->formatter->root))
            ->appendChild(new \DOMElement($this->formatter->nodeName));

        $node->setAttribute("name", "foo");
        $node->setAttribute("value", "bar");

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
                [
                    "name" => "foo",
                    "value" => "bar",
                ],
                "row" => [
                    [
                        "name" => "fruit",
                        "value" => "banana",
                    ],
                    [
                        "name" => "type",
                        "value" => "fruit",
                    ],
                    "orange"=> [
                        [
                            "name" => "type",
                            "value" => "fruit",
                        ]
                    ],
                    "banana"=> [
                        [
                            "name" => "like",
                            "value" => "Yes",
                        ]
                    ]
                ]
            ];

        $dom = new \DOMDocument("1.0");
        $base = $dom->appendChild(new \DOMElement($this->formatter->root));
        /** @var \DOMElement $node */
        $node = $base->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "foo");
        $node->setAttribute("value", "bar");

        $baz = $base->appendChild(new \DOMElement($this->formatter->rowName));
        $node = $baz->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "fruit");
        $node->setAttribute("value", "banana");
        $node = $baz->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "type");
        $node->setAttribute("value", "fruit");

        $baz = $base->appendChild(new \DOMElement($this->formatter->rowName));
        $orange = $baz->appendChild(new \DOMElement("orange"));
        $node = $orange->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "type");
        $node->setAttribute("value", "fruit");
        $banana = $baz->appendChild(new \DOMElement("banana"));
        $node = $banana->appendChild(new \DOMElement($this->formatter->nodeName));
        $node->setAttribute("name", "like");
        $node->setAttribute("value", "Yes");

        $data = $this->formatter->rawDecode($dom->saveXML());
        $this->assertEquals($expectedData, $data);

        $expectedData = [
            "foo" => "bar",
            "row" => [
                "fruit" => "banana",
                "type" => "fruit",
                "orange"=>[
                    "type" => "fruit"
                ],
                "banana"=>[
                    "like" => "Yes"
                ]
            ],
        ];

        $data = $this->formatter->decode($dom->saveXML());

        $this->assertEquals($expectedData, $data->getData());
    }
}
