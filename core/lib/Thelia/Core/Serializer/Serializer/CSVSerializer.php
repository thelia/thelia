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
 * Class CSVSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class CSVSerializer implements SerializerInterface
{
    public function getId()
    {
        return 'thelia.csv';
    }

    public function getName()
    {
        return 'CSV';
    }

    public function getExtension()
    {
        return 'csv';
    }

    public function getMimeType()
    {
        return 'text/csv';
    }

    public function wrapOpening()
    {
        // TODO: Implement wrapOpening() method.
    }

    public function serialize($data)
    {
        // TODO: Implement serialize() method.
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
