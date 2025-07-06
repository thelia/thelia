<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Serializer\Serializer;

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class XMLSerializer.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class XMLSerializer extends AbstractSerializer
{
    private readonly XmlEncoder $xmlEncoder;
    private int|bool|null $xmlDataStart = null;
    private string $rootNodeName = 'root';
    private string $dataNodeName = 'data';

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->xmlEncoder = new XmlEncoder(
            [
                XmlEncoder::ROOT_NODE_NAME => 'data',
            ],
        );
    }

    public function getId(): string
    {
        return 'thelia.xml';
    }

    public function getName(): string
    {
        return 'XML';
    }

    public function getExtension(): string
    {
        return 'xml';
    }

    public function getMimeType(): string
    {
        return 'application/xml';
    }
    public function getDataNodeName(): string
    {
        return $this->dataNodeName;
    }

    public function setDataNodeName(string $dataNodeName): self
    {
        $this->dataNodeName = $dataNodeName;

        return $this;
    }

    public function prepareFile(\SplFileObject $fileObject): void
    {
        $this->xmlDataStart = null;

        $fileObject->fwrite(
            '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<' . $this->rootNodeName . '>' . PHP_EOL,
        );
    }

    public function serialize($data): string
    {
        $xml = $this->xmlEncoder->encode($data, 'array');

        if (null === $this->xmlDataStart) {
            $this->xmlDataStart = strpos($xml, '<' . $this->dataNodeName . '>');
        }

        return substr($xml, $this->xmlDataStart, -1);
    }

    public function separator(): string
    {
        return PHP_EOL;
    }

    public function finalizeFile(\SplFileObject $fileObject): void
    {
        $fileObject->fwrite(PHP_EOL . '</' . $this->rootNodeName . '>');
    }

    public function unserialize(\SplFileObject $fileObject): array
    {
        $unserializedXml = $this->xmlEncoder->decode(file_get_contents($fileObject->getPathname()), 'null');

        return reset($unserializedXml);
    }
}
