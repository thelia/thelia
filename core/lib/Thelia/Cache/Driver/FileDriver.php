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

use Doctrine\Common\Cache\FilesystemCache;
use Thelia\Model\ConfigQuery;


/**
 * Class FileDriver
 * @package Thelia\Cache\Driver
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FileDriver extends BaseCacheDriver {

    const CONFIG_DIRECTORY = 'tcache_file_directory';

    const DEFAULT_DIRECTORY = "doctrine";

    const CONFIG_EXTENSION = 'tcache_file_extension';

    /**
     * Init the cache.
     */
    public function init()
    {
        $directory = ConfigQuery::read(self::CONFIG_DIRECTORY, THELIA_CACHE_DIR . self::DEFAULT_DIRECTORY);
        $extension = ConfigQuery::read(self::CONFIG_EXTENSION, null);

        $this->cache = new FilesystemCache($directory, $extension);
    }

} 