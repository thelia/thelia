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

use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class JSONSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class JSONSerializer extends AbstractSerializer
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

    public function prepareFile(\SplFileObject $fileObject)
    {
        $fileObject->fwrite('[');
    }

    public function serialize($data)
    {
        return json_encode($data);
    }

    public function separator()
    {
        return ',' . PHP_EOL;
    }

    public function finalizeFile(\SplFileObject $fileObject)
    {
        $fileObject->fwrite(']');
    }

    public function unserialize(\SplFileObject $fileObject)
    {
        return json_decode(file_get_contents($fileObject->getPathname()), true);
    }
}
