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


namespace Thelia\Cache\Driver;

use Doctrine\Common\Cache\ApcCache;


/**
 * Class FileDriver
 * @package Thelia\Cache\Driver
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ApcDriver extends BaseCacheDriver
{

    /**
     * Init the cache.
     */
    public function initDriver(array $params = null)
    {
        $this->cache = new ApcCache();
    }

} 