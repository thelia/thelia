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

    /**
     * @var string Root node name
     */
    private $rootNodeName = 'root';

    /**
     * @var string Data node name
     */
    private $dataNodeName = 'data';

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->xmlEncoder = new XmlEncoder;
        $this->xmlEncoder->setRootNodeName($this->dataNodeName);
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

    /**
     * Get root node name
     *
     * @return string Root node name
     */
    public function getRootNodeName()
    {
        return $this->rootNodeName;
    }

    /**
     * Set root node name
     *
     * @param string $rootNodeName Root node name
     *
     * @return $this Return $this, allow chaining
     */
    public function setRootNodeName($rootNodeName)
    {
        $this->rootNodeName = $rootNodeName;

        return $this;
    }

    /**
     * Get data node name
     *
     * @return string Root node name
     */
    public function getDataNodeName()
    {
        return $this->dataNodeName;
    }

    /**
     * Set data node name
     *
     * @param string $dataNodeName Root node name
     *
     * @return $this Return $this, allow chaining
     */
    public function setDataNodeName($dataNodeName)
    {
        $this->dataNodeName = $dataNodeName;
        $this->xmlEncoder->setRootNodeName($this->dataNodeName);

        return $this;
    }

    public function prepareFile(\SplFileObject $fileObject)
    {
        $this->xmlDataStart = null;

        $fileObject->fwrite(
            '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<' . $this->rootNodeName . '>' . PHP_EOL
        );
    }

    public function serialize($data)
    {
        $xml = $this->xmlEncoder->encode($data, 'array');

        if ($this->xmlDataStart === null) {
            $this->xmlDataStart = strpos($xml, '<' . $this->dataNodeName . '>');
        }

        return substr($xml, $this->xmlDataStart, -1);
    }

    public function separator()
    {
        return PHP_EOL;
    }

    public function finalizeFile(\SplFileObject $fileObject)
    {
        $fileObject->fwrite(PHP_EOL . '</' . $this->rootNodeName . '>');
    }

    public function unserialize(\SplFileObject $fileObject)
    {
        $unserializedXml = $this->xmlEncoder->decode(file_get_contents($fileObject->getPathname()), 'null');

        return reset($unserializedXml);
    }
}
