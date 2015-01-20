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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Exception\InvalidModuleException;
use Thelia\Module\ModuleManagement;

/**
 * Class ModuleRefreshCommand
 * Refresh modules list
 *
 * @package Thelia\Command
 * @author  Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ModuleRefreshCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('module:refresh')
            ->setDescription('Refresh modules list');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $moduleManagement = new ModuleManagement;
            $moduleManagement->updateModules($this->getContainer());
        } catch (InvalidModuleException $ime) {
            throw new \RuntimeException(
                sprintf('One or more modules could not be refreshed : %s', $ime->getErrorsAsString("\n"))
            );
        } catch (\Exception $e) {
            throw new \RuntimeException(
                sprintf('Refresh modules list fail with Exception : [%d] %s', $e->getCode(), $e->getMessage())
            );
        }

        if (method_exists($output, 'renderBlock')) {
            $output->renderBlock(
                [
                    '',
                    'Modules list successfully refreshed',
                    ''
                ],
                'bg=green;fg=black'
            );
        }
    }
}
