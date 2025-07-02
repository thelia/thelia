<?php

declare(strict_types=1);

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

use Symfony\Component\Console\Attribute\AsCommand;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;

#[AsCommand(name: 'image-cache:clear', description: 'Empty part or whole web space image cache')]
class ClearImageCache extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('subdir', InputArgument::OPTIONAL, 'Clear only the specified subdirectory')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        new Request();

        try {
            $event = new ImageEvent();

            $subdir = $input->getArgument('subdir');

            if (null !== $subdir) {
                $event->setCacheSubdirectory($subdir);
            }

            $this->getDispatcher()->dispatch($event, TheliaEvents::IMAGE_CLEAR_CACHE);

            $output->writeln(sprintf('%s image cache successfully cleared.', null === $subdir ? 'Entire' : ucfirst($subdir)));
        } catch (Exception $exception) {
            $output->writeln(sprintf('Failed to clear image cache: %s', $exception->getMessage()));

            return 1;
        }

        return 0;
    }
}
