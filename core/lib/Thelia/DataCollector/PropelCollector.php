<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
namespace Thelia\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Propel\Runtime\Propel;
use Psr\Log\LoggerInterface;

/**
 * Class PropelCollector
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class PropelCollector extends DataCollector implements Renderable, LoggerInterface {

    public function __construct()
    {
        $serviceContainer = Propel::getServiceContainer();
        $serviceContainer->setLogger('debugBarLogger', $this);
    }

    /**
     * Called by the DebugBar when data needs to be collected
     *
     * @return array Collected data
     */
    function collect()
    {
        // TODO: Implement collect() method.
    }

    /**
     * Returns the unique name of the collector
     *
     * @return string
     */
    function getName()
    {
        // TODO: Implement getName() method.
    }

    public function getWidgets()
    {
        return array(
            "propel" => array(
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => "propel 2",
                "default" => "[]"
            ),
            "propel:badge" => array(
                "map" => "propel.nb_statements",
                "default" => 0
            )
        );
    }
}