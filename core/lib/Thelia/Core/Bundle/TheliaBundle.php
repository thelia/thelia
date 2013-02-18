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
use Symfony\Component\DependencyInjection\Scope;

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

class TheliaBundle extends Bundle
{
    /**
     *
     * Construct the depency injection builder
     *
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */

    public function build(ContainerBuilder $container)
    {
        $container->addScope( new Scope('request'));

        $container->register('request', 'Symfony\Component\HttpFoundation\Request')
                ->setSynthetic(true);

        $container->register('controller.default','Thelia\Controller\DefaultController');
        $container->register('matcher.default','Thelia\Routing\Matcher\DefaultMatcher')
                ->addArgument(new Reference('controller.default'));

        $container->register('matcher.action', 'Thelia\Routing\Matcher\ActionMatcher');

        $container->register('matcher','Thelia\Routing\TheliaMatcherCollection')
                ->addMethodCall('add', array(new Reference('matcher.default'), -255))
                ->addMethodCall('add', array(new Reference('matcher.action'), -200))
                //->addMethodCall('add','a matcher class (instance or class name)

        ;

        $container->register('resolver', 'Symfony\Component\HttpKernel\Controller\ControllerResolver');

        $container->register('tpex', 'Thelia\Tpex\Tpex');
        
        $container->register('parser','Thelia\Core\Template\Parser')
                ->addArgument(new Reference('service_container'))
                ->addArgument(new Reference('tpex'))
        ;
        /**
         * RouterListener implements EventSubscriberInterface and listen for kernel.request event
         */
        $container->register('listener.router', 'Symfony\Component\HttpKernel\EventListener\RouterListener')
                ->addArgument(new Reference('matcher'))
        ;

        /**
         * @TODO add an other listener on kernel.request for checking some params Like check if User is log in, set the language and other.
         *
         * $container->register()
         *
         *
         * $container->register('listener.request', 'Thelia\Core\EventListener\RequestListener')
         *      ->addArgument(new Reference('');
         * ;
         */

        $container->register('thelia.listener.view','Thelia\Core\EventListener\ViewListener')
                ->addArgument(new Reference('service_container'))
        ;



        $container->register('dispatcher','Symfony\Component\EventDispatcher\EventDispatcher')
                ->addArgument(new Reference('service_container'))
                ->addMethodCall('addSubscriber', array(new Reference('listener.router')))
                ->addMethodCall('addSubscriber', array(new Reference('thelia.listener.view')))
        ;
        
        
        // TODO : save listener from plugins
        
        $container->getDefinition('matcher.action')->addMethodCall("setDispatcher", array(new Reference('dispatcher')));

        $container->register('http_kernel','Thelia\Core\TheliaHttpKernel')
            ->addArgument(new Reference('dispatcher'))
            ->addArgument(new Reference('service_container'))
            ->addArgument(new Reference('resolver'))
        ;

        // DEFINE DEFAULT PARAMETER LIKE

        /**
         * @TODO learn about container compilation
         */

    }
}
