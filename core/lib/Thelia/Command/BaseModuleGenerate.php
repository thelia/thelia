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

namespace Thelia\Command;

use Thelia\Model\Module;

/**
 * base class for module commands
 *
 * Class BaseModuleGenerate
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
abstract class BaseModuleGenerate extends ContainerAwareCommand
{
    protected $module;
    protected $moduleDirectory;

    protected $reservedKeyWords = array(
         'thelia'
     );

    protected $neededDirectories = array(
         'Config',
         'Model',
         'Loop',
         'Command',
         'Controller',
         'EventListeners',
         'I18n',
         Module::ADMIN_INCLUDES_DIRECTORY_NAME,
         'templates',
         'Hook',
     );

    protected function verifyExistingModule()
    {
        if (file_exists($this->moduleDirectory)) {
            throw new \RuntimeException(sprintf("%s module already exists", $this->module));
        }
    }

    protected function formatModuleName($name)
    {
        if (in_array(strtolower($name), $this->reservedKeyWords)) {
            throw new \RuntimeException(sprintf("%s module name is a reserved keyword", $name));
        }

        return ucfirst($name);
    }
}
