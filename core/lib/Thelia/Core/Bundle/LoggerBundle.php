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

class LoggerBundle extends Bundle
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
        $container->setParameter("logger.class", "\Thelia\Log\Tlog");
        $container->register("logger","%logger.class%")
                ->addArgument(new Reference('service_container'));
        
        $kernel = $container->get('kernel');
        
        if($kernel->isDebug())
        {
//            $debug = function ($query, $parameters)
//            {
//                
//                $pattern = '(^' . preg_quote(dirname(__FILE__)) . '(\\.php$|[/\\\\]))'; // can be static
//                foreach (debug_backtrace() as $backtrace) {
//                        if (isset($backtrace["file"]) && !preg_match($pattern, $backtrace["file"])) { // stop on first file outside NotORM source codes
//                                break;
//                        }
//                }
//                file_put_contents(THELIA_ROOT . 'log/request_debug.log', "$backtrace[file]:$backtrace[line]:$query", FILE_APPEND);
//                file_put_contents(THELIA_ROOT . 'log/request_debug.log', is_scalar($parameters) ? $parameters : print_r($parameters, true), FILE_APPEND);
//                
//            };
//            
//            $container->getDefinition('database')
//                        ->addMethodCall('setDebug', array($debug));
            
            $container->get('database')
                    ->setLogger($container->get('logger'));
        }
    }
}
