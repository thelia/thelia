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
namespace Thelia\Service\DataTransfer;

use ErrorException;
use DirectoryIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\Archiver\AbstractArchiver;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Event\ImportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Serializer\AbstractSerializer;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\ImportExport\Import\AbstractImport;
use Thelia\Model\Import;
use Thelia\Model\ImportCategory;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\Lang;

/**
 * Class ImportHandler.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected SerializerManager $serializerManager,
        protected ArchiverManager $archiverManager
    ) {
    }

    /**
     * @throws ErrorException
     */
    public function getImport(int $importId, bool $dispatchException = false): ?Import
    {
        $import = (new ImportQuery())->findPk($importId);

        if ($import === null && $dispatchException) {
            throw new ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the imports',
                    [
                        '%id' => $importId,
                    ]
                )
            );
        }

        return $import;
    }

    /**
     * @throws ErrorException
     */
    public function getImportByRef(string $importRef, bool $dispatchException = false): ?Import
    {
        $import = (new ImportQuery())->findOneByRef($importRef);

        if ($import === null && $dispatchException) {
            throw new ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%ref" in the imports',
                    [
                        '%ref' => $importRef,
                    ]
                )
            );
        }

        return $import;
    }

    /**
     * @throws ErrorException
     */
    public function getCategory(int $importCategoryId, bool $dispatchException = false): ?ImportCategory
    {
        $category = (new ImportCategoryQuery())->findPk($importCategoryId);

        if ($category === null && $dispatchException) {
            throw new ErrorException(
                Translator::getInstance()->trans(
                    'There is no id "%id" in the import categories',
                    [
                        '%id' => $importCategoryId,
                    ]
                )
            );
        }

        return $category;
    }

    public function import(Import $import, File $file, Lang $language = null): ImportEvent
    {
        $archiver = $this->matchArchiverByExtension($file->getFilename());

        if ($archiver instanceof AbstractArchiver) {
            $file = $this->extractArchive($file, $archiver);
        }

        $serializer = $this->matchSerializerByExtension($file->getFilename());

        if (!$serializer instanceof AbstractSerializer) {
            throw new FormValidationException(
                Translator::getInstance()->trans(
                    'The extension "%extension" is not allowed',
                    [
                        '%extension' => pathinfo($file->getFilename(), \PATHINFO_EXTENSION),
                    ]
                )
            );
        }

        $importHandleClass = $import->getHandleClass();

        /** @var AbstractImport $instance */
        $instance = new $importHandleClass();

        // Configure handle class
        $instance->setLang($language);
        $instance->setFile($file);

        // Process import
        $event = new ImportEvent($instance, $serializer);

        $this->eventDispatcher->dispatch($event, TheliaEvents::IMPORT_BEGIN);

        $errors = $this->processImport($event->getImport(), $event->getSerializer());

        $event->setErrors($errors);

        $this->eventDispatcher->dispatch($event, TheliaEvents::IMPORT_FINISHED);

        $this->eventDispatcher->dispatch($event, TheliaEvents::IMPORT_SUCCESS);

        return $event;
    }

    public function matchArchiverByExtension(string $fileName): ?AbstractArchiver
    {
        /** @var AbstractArchiver $archiver */
        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            if (stripos($fileName, '.'.$archiver->getExtension()) !== false) {
                return $archiver;
            }
        }

        return null;
    }

    public function matchSerializerByExtension($fileName): ?AbstractSerializer
    {
        /** @var AbstractSerializer $serializer */
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            if (stripos((string) $fileName, '.'.$serializer->getExtension()) !== false) {
                return $serializer;
            }
        }

        return null;
    }

    public function extractArchive(File $file, ArchiverInterface $archiver): File
    {
        $archiver->open($file->getPathname());

        $extractPath = \dirname($archiver->getArchivePath()).DS.uniqid('', true);

        $archiver->extract($extractPath);

        /** @var DirectoryIterator $item */
        foreach (new DirectoryIterator($extractPath) as $item) {
            if (!$item->isDot() && $item->isFile()) {
                $file = new File($item->getPathname());

                break;
            }
        }

        return $file;
    }

    protected function processImport(AbstractImport $import, SerializerInterface $serializer): array
    {
        $errors = [];

        $import->setData($serializer->unserialize($import->getFile()->openFile('r')));

        foreach ($import as $data) {
            $import->checkMandatoryColumns($data);

            $error = $import->importData($data);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
