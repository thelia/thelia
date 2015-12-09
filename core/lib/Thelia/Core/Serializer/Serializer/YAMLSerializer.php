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

use Symfony\Component\Yaml\Yaml;
use Thelia\Core\Serializer\AbstractSerializer;

/**
 * Class YAMLSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class YAMLSerializer extends AbstractSerializer
{
    public function getId()
    {
        return 'thelia.yml';
    }

    public function getName()
    {
        return 'YAML';
    }

    public function getExtension()
    {
        return 'yaml';
    }

    public function getMimeType()
    {
        return 'application/x-yaml';
    }

    public function serialize($data)
    {
        return Yaml::dump([$data]);
    }

    public function unserialize()
    {
        // TODO: Implement unserialize() method.
    }
}
