<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*		email : info@thelia.net                                              */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.     */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Autoload;

use Symfony\Component\ClassLoader\UniversalClassLoader;

/**
 * TheliaUniversalClassLoader
 *
 * extends Symfony\Component\ClassLoader\UniversalClassLoader
 *
 * This class respect PSR-0 autoloading standard and allow to load traditionnal Thelia classes.
 * 
 * classMap can be used to.
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 *
 */

class TheliaUniversalClassLoader extends UniversalClassLoader
{
    private $directories = array();
    private $classMap = array();

    /**
     *
     * add path directory where autoload can search files
     *
     * @param string $directory
     */
    public function addDirectory($directory)
    {
        $this->directories[] = $directory;
    }

    /**
     *
     * add multiple path directory in an array where autoload can search files
     *
     * @param array $directories
     */
    public function addDirectories(array $directories)
    {
        foreach ($directories as $directory) {
            $this->addDirectory($directory);
        }
    }

    /**
     *
     * return directories where traditional Thelia classes can be found
     *
     * @return array an Array of directories
     */
    public function getDirectories()
    {
        return $this->directories;
    }
    
    /**
     * @param array $classMap Class to filename map
     */
    public function addClassMap(array $classMap)
    {
        if ($this->classMap) {
            $this->classMap = array_merge($this->classMap, $classMap);
        } else {
            $this->classMap = $classMap;
        }
    }

    /**
     *
     * Finds the path to the file where the class is defined.
     *
     * @param  string      $class The name of the class
     * @return string|null The path, if found
     */
    public function findFile($class)
    {
        if (isset($this->classMap[$class])) {
            return $this->classMap[$class];
        }
        
        foreach ($this->directories as $directory) {

            if (is_file($directory.DIRECTORY_SEPARATOR.$class.".class.php")) {
                return $directory.DIRECTORY_SEPARATOR.$class.".class.php";
            }

            if (is_file($directory.DIRECTORY_SEPARATOR.$class.".interface.php")) {
                return $directory.DIRECTORY_SEPARATOR.$class.".interface.php";
            }

        }

        return parent::findFile($class);
    }

}
