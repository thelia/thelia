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

namespace Thelia\Exception;

use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;

/**
 * Thrown when an Abstract method has not been implemented
 *
 * @package Exception
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class NotImplementedException extends BadMethodCallException
{
}
