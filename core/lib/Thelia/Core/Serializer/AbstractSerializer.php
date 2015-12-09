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


namespace Thelia\Core\Serializer;

/**
 * Class AbstractSerializer
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
abstract class AbstractSerializer implements SerializerInterface
{
    public function prepareFile(\SplFileObject $fileObject)
    {
    }

    public function separator()
    {
    }

    public function finalizeFile(\SplFileObject $fileObject)
    {
    }
}
