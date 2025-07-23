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

namespace Thelia\Files\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\File\FileToggleVisibilityEvent;
use Thelia\Files\FileManager;
use Thelia\Files\FileModelInterface;

readonly class FileVisibilityService
{
    public function __construct(
        private FileManager $fileManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws \Exception If visibility toggle fails
     */
    public function toggleFileVisibility(
        EventDispatcherInterface $eventDispatcher,
        int $fileId,
        string $parentType,
        string $objectType,
        string $eventName,
        string $moduleRight = 'thelia',
    ): string {
        $modelInstance = $this->fileManager->getModelInstance($objectType, $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($fileId);

        if (null === $model) {
            throw new \Exception('File not found');
        }

        // Feed event
        $event = new FileToggleVisibilityEvent(
            $modelInstance->getQueryInstance(),
            $fileId,
        );

        // Dispatch Event to the Action
        try {
            $eventDispatcher->dispatch($event, $eventName);
            $message = $this->translator->trans(
                '%type% visibility updated',
                ['%type%' => ucfirst($objectType)],
            );
        } catch (\Exception $exception) {
            $message = $this->translator->trans(
                'Fail to update %type% visibility: %err%',
                ['%type%' => $objectType, '%err%' => $exception->getMessage()],
            );

            throw new \Exception($message, 0, $exception);
        }

        return $message;
    }

    public function getFileModelInstance(string $objectType, string $parentType): FileModelInterface
    {
        return $this->fileManager->getModelInstance($objectType, $parentType);
    }

    public function getFileById(string $objectType, string $parentType, int $fileId): ?FileModelInterface
    {
        $modelInstance = $this->fileManager->getModelInstance($objectType, $parentType);

        return $modelInstance->getQueryInstance()->findPk($fileId);
    }
}
