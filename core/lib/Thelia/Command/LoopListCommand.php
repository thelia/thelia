<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheliaSmarty\Template\Plugins\TheliaLoop;

class LoopListCommand extends ContainerAwareCommand
{
    protected $theliaLoop;

    public function __construct(TheliaLoop $theliaLoop)
    {
        parent::__construct();
        $this->theliaLoop = $theliaLoop;
    }

    protected function configure(): void
    {
        $this
            ->setName('loop:list')
            ->setDescription('List the loops')
        ;
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loops = $this->theliaLoop->getLoopList();
        ksort($loops);

        $helper = new Table($output);

        foreach ($loops as $name => $class) {
            $helper->addRow([$name, $class]);
        }

        $helper
            ->setHeaders(['Name', 'Class'])
            ->render()
        ;

        return 0;
    }
}
