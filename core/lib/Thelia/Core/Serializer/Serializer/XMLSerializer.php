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
use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class XMLSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class XMLSerializer extends AbstractSerializer
{
    /**
     * @var \Symfony\Component\Serializer\Encoder\XmlEncoder An xml encoder instance
     */
    private $xmlEncoder;

    /**
     * @var integer Position of data start
     */
    private $xmlDataStart;

    private $rootNodeName = 'data';

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->xmlEncoder = new XmlEncoder;
        $this->xmlEncoder->setRootNodeName($this->rootNodeName);
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

    public function prepareFile(\SplFileObject $fileObject)
    {
        $this->xmlDataStart = null;

        $fileObject->fwrite('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<root>' . PHP_EOL);
    }

    public function serialize($data)
    {
        $xml = $this->xmlEncoder->encode($data, 'array');

        if ($this->xmlDataStart === null) {
            $this->xmlDataStart = strpos($xml, '<' . $this->rootNodeName . '>');
        }

        return substr($xml, $this->xmlDataStart, -1);
    }

    public function separator()
    {
        return PHP_EOL;
    }

    public function finalizeFile(\SplFileObject $fileObject)
    {
        $fileObject->fwrite(PHP_EOL . '</root>');
    }

    public function unserialize()
    {
        // TODO: Implement unserialize() method.
    }
}
