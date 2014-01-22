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

namespace Thelia\Core;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * cli application for Thelia
 * Class Application
 * @package Thelia\Core
 * mfony\Component\HttpFoundation\Session\Session
 */
class Application extends BaseApplication
{

    public $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;

        parent::__construct("Thelia", Thelia::THELIA_VERSION);

        $this->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $kernel->getEnvironment()));
        $this->getDefinition()->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.'));
    }

    public function getKernel()
    {
        return $this->kernel;
    }

    public function getContainer()
    {
        return $this->kernel->getContainer();
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        foreach ($container->getParameter("command.definition") as $command) {
            $r = new \ReflectionClass($command);

            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $this->add($r->newInstance());
            }
        }

    }
}
