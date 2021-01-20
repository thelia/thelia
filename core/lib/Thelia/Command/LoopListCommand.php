<?php


namespace Thelia\Command;


use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheliaSmarty\Template\Plugins\TheliaLoop;

class LoopListCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('loop:list')
            ->setDescription('List the loops')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var TheliaLoop $theliaLoop */
        $theliaLoop = $this->getContainer()->get(TheliaLoop::class);
        $loops = $theliaLoop->getLoopList();
        ksort($loops);

        $helper = new Table($output);

        foreach ($loops as $name => $class) {
            $helper->addRow([$name, $class]);
        }

        $helper
            ->setHeaders(["Name", "Class"])
            ->render()
        ;

        return 0;
    }
}