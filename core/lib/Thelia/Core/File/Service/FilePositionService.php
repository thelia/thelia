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

namespace Thelia\Core\File\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\UpdateFilePositionEvent;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\FileModelInterface;

readonly class FilePositionService
{
    public function __construct(
        private FileManager $fileManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws \Exception If position update fails
     */
    public function updateFilePosition(
        EventDispatcherInterface $eventDispatcher,
        string $parentType,
        int $fileId,
        string $objectType,
        string $eventName,
        int $position,
        string $moduleRight = 'thelia',
    ): string {
        $modelInstance = $this->fileManager->getModelInstance($objectType, $parentType);
        $model = $modelInstance->getQueryInstance()->findPk($fileId);

        if (null === $model) {
            throw new \Exception('File not found');
        }

        // Feed event
        $event = new UpdateFilePositionEvent(
            $modelInstance->getQueryInstance(),
            $fileId,
            UpdateFilePositionEvent::POSITION_ABSOLUTE,
            $position,
        );

        // Dispatch Event to the Action
        try {
            $eventDispatcher->dispatch($event, $eventName);
            $message = $this->translator->trans(
                '%type% position updated',
                ['%type%' => ucfirst($objectType)],
            );
        } catch (\Exception $exception) {
            $message = $this->translator->trans(
                'Fail to update %type% position: %err%',
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
