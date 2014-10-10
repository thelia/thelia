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

use Doctrine\Common\Cache\ArrayCache;


/**
 * Class ArrayDriver
 * @package Thelia\Cache\Driver
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ArrayDriver extends BaseCacheDriver
{

    /**
     * Init the cache.
     */
    public function init(array $params = null)
    {
        $this->initDefault($params);

        $this->cache = new ArrayCache();
    }

    /**
     * No reference needed for ArrayDriver
     *
     * @param string $ref the reference key
     * @param string $key the cache key
     *
     * @return boolean false
     */
    protected function addRef($ref, $key)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteRef($ref)
    {
        return 0;
    }


}