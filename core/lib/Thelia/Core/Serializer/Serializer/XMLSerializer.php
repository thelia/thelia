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

namespace Thelia\Core\Serializer\Serializer;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Thelia\Core\Serializer\SerializerInterface;

/**
 * Class XMLSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class XMLSerializer implements SerializerInterface
{
    /**
     * @var \Symfony\Component\Serializer\Encoder\XmlEncoder An xml encoder instance
     */
    private $xmlEncoder;

    /**
     * @var integer Position of data start
     */
    private $xmlDataStart;

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->xmlEncoder = new XmlEncoder;
    }

    public function getId()
    {
        return 'thelia.xml';
    }

    public function getName()
    {
        return 'XML';
    }

    public function getExtension()
    {
        return 'xml';
    }

    public function getMimeType()
    {
        return 'application/xml';
    }

    public function wrapOpening()
    {
        $this->xmlEncoder->setRootNodeName('data');
        $this->xmlDataStart = null;

        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<root>' . PHP_EOL;
    }

    public function serialize($data)
    {
        $xml = $this->xmlEncoder->encode($data, 'array');

        if ($this->xmlDataStart === null) {
            $this->xmlDataStart = strpos($xml, '<data>');
        }

        return substr($xml, $this->xmlDataStart, -1);
    }

    public function separator()
    {
        return PHP_EOL;
    }

    public function wrapClosing()
    {
        return PHP_EOL . '</root>';
    }

    public function unserialize()
    {
        // TODO: Implement unserialize() method.
    }
}
