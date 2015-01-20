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

namespace Thelia\Module;

use Propel\Runtime\Connection\ConnectionInterface;

interface BaseModuleInterface
{
    public function install(ConnectionInterface $con);

    public function preActivation(ConnectionInterface $con);

    public function postActivation(ConnectionInterface $con);

    public function preDeactivation(ConnectionInterface $con);

    public function postDeactivation(ConnectionInterface $con);

    public function destroy(ConnectionInterface $con, $deleteModuleData = false);
}
