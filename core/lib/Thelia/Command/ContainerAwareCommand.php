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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Thelia\Core\Application;
use Thelia\Core\Translation\Translator;

/**
 * Command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ContainerAwareCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if (null === $this->container) {
            /** @var Application $application */
            $application = $this->getApplication();
            $this->container = $application->getKernel()->getContainer();
        }

        return $this->container;
    }

    /**
     * @see ContainerAwareInterface::setContainer()
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcher
     */
    public function getDispatcher()
    {
        $container = $this->getContainer();

        // Initialize Thelia translator, if not already done.
        try {
            Translator::getInstance();
        } catch (\Exception $ex) {
            $this->container->get('thelia.translator');
        }

        return $container->get('event_dispatcher');
    }
}
