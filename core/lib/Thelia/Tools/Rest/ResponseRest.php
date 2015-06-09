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
namespace Thelia\Tools\Rest;

use Thelia\Core\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

/**
 * Class ResponseRest Create a serialized Response
 *
 * @package Thelia\Tools\Rest
 */
class ResponseRest extends Response
{
    /** @var Response Response Object */
    protected $response;

    /** @var string Response format */
    protected $format;

    /**
     * Constructor.
     *
     * @param array   $data    Array to be serialized
     * @param string  $format  serialization format, text, xml or json available
     * @param integer $status  The response status code
     * @param array   $headers An array of response headers
     *
     * @throws \InvalidArgumentException When the HTTP status code is not valid
     *
     * @api
    */
    public function __construct($data = null, $format = 'json', $status = 200, $headers = array())
    {
        parent::__construct('', $status, $headers);

        if ($format == 'text') {
            if (isset($data)) {
                $this->setContent($data);
            }

            $this->headers->set('Content-Type', 'text/plain');
        } else {
            $this->format = $format;
            $serializer = $this->getSerializer();

            if (isset($data)) {
                $this->setContent($serializer->serialize($data, $this->format));
            }

            $this->headers->set('Content-Type', 'application/' . $this->format);
        }
    }

    /**
     * Set Content to be serialized in the response, array or object
     *
     * @param array $data array or object to be serialized
     *
     * @return $this
     */
    public function setRestContent($data)
    {
        $serializer = $this->getSerializer();

        if (isset($data)) {
            $this->setContent($serializer->serialize($data, $this->format));
        }

        return $this;
    }

    /**
     * Get Serializer
     *
     * @return Serializer
     */
    protected function getSerializer()
    {
        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());

        return new Serializer($normalizers, $encoders);
    }
}
