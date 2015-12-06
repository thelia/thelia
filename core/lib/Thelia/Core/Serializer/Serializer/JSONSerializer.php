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
 * Class JSONSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class JSONSerializer implements SerializerInterface
{
    public function getId()
    {
        return 'thelia.json';
    }

    public function getName()
    {
        return 'JSON';
    }

    public function getExtension()
    {
        return 'json';
    }

    public function getMimeType()
    {
        return 'application/json';
    }

    public function wrapOpening()
    {
        return '[';
    }

    public function serialize($data)
    {
        return json_encode($data);
    }

    public function separator()
    {
        return ',';
    }

    public function wrapClosing()
    {
        return ']';
    }

    public function unserialize()
    {
        // TODO: Implement decode() method.
    }
}
