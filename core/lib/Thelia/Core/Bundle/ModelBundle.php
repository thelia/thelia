<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	email : info@thelia.net                                                      */
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
namespace Thelia\Core\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Thelia\Tools\DIGenerator;

/**
 * First Bundle use in Thelia
 * It initialize dependency injection container.
 *
 * @TODO load configuration from thelia plugin
 * @TODO register database configuration.
 *
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class ModelBundle extends Bundle
{
    /**
     *
     * Construct the depency injection builder
     *
     * Reference all Model in the Container here
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */

    public function build(ContainerBuilder $container)
    {
        foreach (DIGenerator::genDiModel(realpath(THELIA_ROOT . "core/lib/Thelia/Model"), array('Base')) as $name => $class) {
            $container->register('model.'.$name, $class)
                    ->addArgument(new Reference("database"));
        }
    }
}
