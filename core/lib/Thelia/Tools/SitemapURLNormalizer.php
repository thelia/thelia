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

namespace Thelia\Tools;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\SerializerAwareNormalizer;
use Thelia\Model\Tools\SitemapURL;

/**
 * Class SitemapURLNormalizer
 * @package Thelia\Tools
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class SitemapURLNormalizer extends SerializerAwareNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = array())
    {
        $normalizeSitemapURL = array(
            'loc' => $this->urlEncode($object->getLoc())
        );
        if (null !== $object->getLastmod()) {
            $normalizeSitemapURL['lastmod'] = $object->getLastmod();
        }
        if (null !== $object->getChangfreq()) {
            $normalizeSitemapURL['changfreq'] = $object->getChangfreq();
        }
        if (null !== $object->getPriotity()) {
            $normalizeSitemapURL['priority'] = $object->getPriotity();
        }

        return $normalizeSitemapURL;
    }

    protected function urlEncode($url)
    {
        return str_replace(array('&', '"', '\'', '<', '>'), array('&amp;', '&apos;', '&quot;', '&gt;', '&lt;'), $url);
    }
    // public function denormalize($data, $class, $format = null) {}

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof SitemapURL;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }
}
