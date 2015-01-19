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

namespace Carousel;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class Carousel extends BaseModule
{
    const DOMAIN_NAME = 'carousel';

    public function preActivation(ConnectionInterface $con = null)
    {
        if (! $this->getConfigValue('is_initialized', false)) {
            $database = new Database($con);

            $database->insertSql(null, array(__DIR__ . '/Config/sql/thelia.sql'));

            $this->setConfigValue('is_initialized', true);
        }
    }

    public function destroy(ConnectionInterface $con = null, $deleteModuleData = false)
    {
        $database = new Database($con);

        $database->insertSql(null, array(__DIR__ . '/Config/sql/destroy.sql'));
    }

    public function getUploadDir()
    {
        return __DIR__ . DS . 'media' . DS . 'carousel';
    }
}
