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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\ModuleQuery;
use Symfony\Component\Console\Helper\QuestionHelper;

/**
 * Class ModulePositionCommand
 * Set modules position
 *
 * @package Thelia\Command
 * @author  Jérôme Billiras <jerome.billiras+github@gmail.com>
 */
class ModulePositionCommand extends ContainerAwareCommand
{
    /**
     * @var \Thelia\Model\ModuleQuery
     */
    protected $moduleQuery;

    /**
     * @var array Modules list
     */
    protected $modulesList = [];

    /**
     * @var array Modules positions list
     */
    protected $positionsList = [];

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->moduleQuery = new ModuleQuery;
    }

    protected function configure()
    {
        $this
            ->setName('module:position')
            ->setDescription('Set module(s) position')
            ->addArgument(
                'modules',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Module in format moduleName:[+|-]position where position is an integer or up or down.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $argsList = $input->getArgument('modules');
        array_walk($argsList, [$this, 'checkModuleArgument']);

        if (!$this->checkPositions($input, $output, $isAbsolute)) {
            return;
        }

        if ($isAbsolute) {
            array_multisort($this->positionsList, SORT_ASC, SORT_REGULAR, $this->modulesList);
        }

        $maxPositionByType = $this->cleanPosition();

        foreach ($this->modulesList as $moduleIdx => $moduleName) {
            $this->moduleQuery->clear();
            $module = $this->moduleQuery->findOneByCode($moduleName);
            $position = $this->positionsList[$moduleIdx];

            if ($position === 'up') {
                $event = new UpdatePositionEvent($module->getId(), UpdatePositionEvent::POSITION_UP);
            } elseif ($position === 'down') {
                $event = new UpdatePositionEvent($module->getId(), UpdatePositionEvent::POSITION_DOWN);
            } else {
                if ($position[0] === '+' || $position[0] === '-') {
                    $position = $module->getPosition() + $position;
                }

                if ($position < 1) {
                    $position = 1;
                }

                $maxPosition = $maxPositionByType[$module->getType()];
                if ($position > $maxPosition) {
                    $position = $maxPosition;
                }

                $event = new UpdatePositionEvent($module->getId(), UpdatePositionEvent::POSITION_ABSOLUTE, $position);
            }

            $this->getDispatcher()->dispatch(TheliaEvents::MODULE_UPDATE_POSITION, $event);
        }

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');
        $formattedBlock = $formatter->formatBlock('Module position(s) updated', 'bg=green;fg=black', true);
        $output->writeln($formattedBlock);
    }

    /**
     * Check a module argument format
     *
     * @param string  $paramValue
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function checkModuleArgument($paramValue)
    {
        if (!preg_match('#^([a-z0-9]+):([\+-]?[0-9]+|up|down)$#i', $paramValue, $matches)) {
            throw new \InvalidArgumentException(
                'Arguments must be in format moduleName:[+|-]position where position is an integer or up or down.'
            );
        }

        $this->moduleQuery->clear();
        $module = $this->moduleQuery->findOneByCode($matches[1]);
        if ($module === null) {
            throw new \RuntimeException(sprintf('%s module does not exists. Try to refresh first.', $matches[1]));
        }

        $this->modulesList[] = $matches[1];
        $this->positionsList[] = $matches[2];
    }

    /**
     * Reorder modules positions (without holes)
     *
     * @return array Maximum position by type
     */
    protected function cleanPosition()
    {
        $modulesType = [];

        $this->moduleQuery->clear();
        $modules = $this->moduleQuery->orderByPosition(Criteria::ASC);

        /** @var \Thelia\Model\Module $module */
        foreach ($modules as $module) {
            if (!isset($modulesType[$module->getType()])) {
                $modulesType[$module->getType()] = 0;
            }

            $module
                ->setPosition(++$modulesType[$module->getType()])
                ->save()
            ;
        }

        return $modulesType;
    }

    /**
     * Check positions consistency
     *
     * @param InputInterface $input     An InputInterface instance
     * @param OutputInterface $output     An OutputInterface instance
     * @param bool            $isAbsolute Set to true or false according to position values
     *
     * @throws \InvalidArgumentException
     *
     * @return bool Continue or stop command
     */
    protected function checkPositions(InputInterface $input, OutputInterface $output, &$isAbsolute = false)
    {
        $isRelative = false;
        foreach (array_count_values($this->positionsList) as $value => $count) {
            if (is_int($value) && $value[0] !== '+' && $value[0] !== '-') {
                $isAbsolute = true;

                if ($count > 1) {
                    throw new \InvalidArgumentException('Two (or more) absolute positions are identical.');
                }
            } else {
                $isRelative = true;
            }
        }

        if ($isAbsolute && $isRelative) {
            /** @var FormatterHelper $formatter */
            $formatter = $this->getHelper('formatter');
            $formattedBlock = $formatter->formatBlock(
                'Mix absolute and relative positions may produce unexpected results !',
                'bg=yellow;fg=black',
                true
            );
            $output->writeln($formattedBlock);

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            $question = new ConfirmationQuestion('<question>Do you want to continue ? y/[n]<question>', false);

            return $helper->ask($input, $output, $question);
        }

        return true;
    }
}
