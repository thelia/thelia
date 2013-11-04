<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Command;

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
         'AdminIncludes'
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
