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

/**
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class TheliaApcUniversalClassLoader extends TheliaUniversalClassLoader
{
    private $prefix;

    /**
     * Constructor
     *
     * Come from Symfony\Component\ClassLoader\ApcUniversalClassLoader
     *
     * @param  string            $prefix
     * @throws \RuntimeException
     */
    public function __construct($prefix)
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException('Unable to use ApcUniversalClassLoader as APC is not enabled.');
        }

        $this->prefix = $prefix;
    }

    /**
     * Finds a file by class name while caching lookups to APC.
     *
     * Come from Symfony\Component\ClassLoader\ApcUniversalClassLoader
     *
     * @param string $class A class name to resolve to file
     *
     * @return string|null The path, if found
     */
    public function findFile($class)
    {
        if (false === $file = apc_fetch($this->prefix.$class)) {
            apc_store($this->prefix.$class, $file = parent::findFile($class));
        }

        return $file;
    }
}
