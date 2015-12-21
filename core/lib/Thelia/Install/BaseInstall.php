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

namespace Thelia\Install;

use Thelia\Install\Exception\AlreadyInstallException;

/**
 * Class BaseInstall
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
abstract class BaseInstall
{
    /** @var bool If Installation wizard is launched by CLI */
    protected $isConsoleMode = true;

    /**
     * Constructor
     *
     * @param bool $verifyInstall Verify if an installation already exists
     *
     * @throws Exception\AlreadyInstallException
     */
    public function __construct($verifyInstall = true)
    {
        // Check if install wizard is launched via CLI
        if (php_sapi_name() == 'cli') {
            $this->isConsoleMode = true;
        } else {
            $this->isConsoleMode = false;
        }
        if (file_exists(THELIA_ROOT . '/local/config/database.yml') && $verifyInstall) {
            throw new AlreadyInstallException("Thelia is already installed");
        }

        $this->exec();
    }

    abstract public function exec();
}
