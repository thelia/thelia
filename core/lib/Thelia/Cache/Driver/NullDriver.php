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


/**
 * Class NullDriver
 * @package Thelia\Cache\Driver
 * @author  Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class NullDriver extends BaseCacheDriver
{


    /**
     * Init the cache.
     */
    public function initDriver(array $params = null)
    {

    }

    public function fetch($id)
    {
        return false;
    }

    public function contains($id)
    {
        return false;
    }

    public function save($id, $data, $refs = [], $lifeTime = null)
    {
        return false;
    }

    public function delete($id)
    {
        return false;
    }

    public function deleteAll()
    {
        return false;
    }

    public function getStats()
    {
        return null;
    }


}