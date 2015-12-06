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

use Thelia\Core\Serializer\SerializerInterface;

/**
 * Class XMLSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class XMLSerializer implements SerializerInterface
{
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
        // TODO: Implement wrapOpening() method.
    }

    public function serialize($data)
    {
        // TODO: Implement serialize() method.
    }

    public function separator()
    {
        // TODO: Implement separator() method.
    }

    public function wrapClosing()
    {
        // TODO: Implement wrapClosing() method.
    }

    public function unserialize()
    {
        // TODO: Implement unserialize() method.
    }
}
