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


namespace Thelia\Core\Template\Element\Overrides;

use Thelia\Core\Template\Element\BaseLoop;

/**
 * Class ArgInitializationOverrideInterface
 * @package Thelia\Core\Template\Element\Overrides
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
interface ArgInitializationOverrideInterface
{
    /**
     * @param BaseLoop $loop
     * @param array $nameValuePairs
     * @return array
     */
    public function initialize(BaseLoop $loop, array $nameValuePairs);
}
