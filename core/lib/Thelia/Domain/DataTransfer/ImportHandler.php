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

namespace Thelia\Domain\DataTransfer;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\Archiver\AbstractArchiver;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Event\ImportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileConfiguration;
use Thelia\Core\Serializer\AbstractSerializer;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Form\Exception\FormValidationException;
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
        protected ArchiverManager $archiverManager,
    ) {
    }

    /**
     * @throws \ErrorException
     */
    public function getImport(int $importId, bool $dispatchException = false): ?Import
    {
        $import = (new ImportQuery())->findPk($importId);

        if (null === $import && $dispatchException) {
            throw new \ErrorException(Translator::getInstance()->trans('There is no id "%id" in the imports', ['%id' => $importId]));
        }

        return $import;
    }

    /**
     * @throws \ErrorException
     */
    public function getImportByRef(string $importRef, bool $dispatchException = false): ?Import
    {
        $import = (new ImportQuery())->findOneByRef($importRef);

        if (null === $import && $dispatchException) {
            throw new \ErrorException(Translator::getInstance()->trans('There is no id "%ref" in the imports', ['%ref' => $importRef]));
        }

        return $import;
    }

    /**
     * @throws \ErrorException
     */
    public function getCategory(int $importCategoryId, bool $dispatchException = false): ?ImportCategory
    {
        $category = (new ImportCategoryQuery())->findPk($importCategoryId);

        if (null === $category && $dispatchException) {
            throw new \ErrorException(Translator::getInstance()->trans('There is no id "%id" in the import categories', ['%id' => $importCategoryId]));
        }

        return $category;
    }

    public function import(Import $import, File $file, ?Lang $language = null): ImportEvent
    {
        $archiver = $this->matchArchiverByExtension($file->getFilename());

        if ($archiver instanceof AbstractArchiver) {
            $file = $this->extractArchive($file, $archiver);
        }

        $serializer = $this->matchSerializerByExtension($file->getFilename());

        if (!$serializer instanceof AbstractSerializer) {
            throw new FormValidationException(Translator::getInstance()->trans('The extension "%extension" is not allowed', ['%extension' => pathinfo($file->getFilename(), \PATHINFO_EXTENSION)]));
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

    /**
     * Extensions the registered serializers and archivers are able to read. This is
     * what the back office may accept, and what it advertises to the administrator.
     *
     * @return list<string>
     */
    public function getAcceptedExtensions(): array
    {
        $extensions = [];

        foreach ($this->serializerManager->getSerializers() as $serializer) {
            $extensions[] = strtolower($serializer->getExtension());
        }

        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            $extensions[] = strtolower($archiver->getExtension());
        }

        return array_values(array_unique($extensions));
    }

    /**
     * Mime types matching getAcceptedExtensions(), for the file input "accept" hint.
     *
     * @return list<string>
     */
    public function getAcceptedMimeTypes(): array
    {
        $mimeTypes = [];

        foreach ($this->serializerManager->getSerializers() as $serializer) {
            $mimeTypes[] = $serializer->getMimeType();
        }

        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            $mimeTypes[] = $archiver->getMimeType();
        }

        return array_values(array_unique($mimeTypes));
    }

    /**
     * Checks an uploaded file name against the formats the import handlers declare,
     * before anything is written to disk. Callers get the same policy the back office
     * displays, so the promise made by the interface is the one that is enforced.
     *
     * @throws FormValidationException when the file may not be imported
     */
    public function validateUpload(string $fileName): void
    {
        $dangerousExtension = FileConfiguration::findExecutableExtension($fileName);

        if (null !== $dangerousExtension) {
            throw new FormValidationException(Translator::getInstance()->trans('The extension "%extension" is not allowed', ['%extension' => $dangerousExtension]));
        }

        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        $acceptedExtensions = $this->getAcceptedExtensions();

        if (!\in_array($extension, $acceptedExtensions, true)) {
            throw new FormValidationException(Translator::getInstance()->trans('The extension "%extension" is not allowed. Accepted formats: %formats', ['%extension' => $extension, '%formats' => implode(', ', $acceptedExtensions)]));
        }
    }

    public function matchArchiverByExtension(string $fileName): ?AbstractArchiver
    {
        $extension = pathinfo($fileName, \PATHINFO_EXTENSION);

        /** @var AbstractArchiver $archiver */
        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            if (0 === strcasecmp($extension, $archiver->getExtension())) {
                return $archiver;
            }
        }

        return null;
    }

    public function matchSerializerByExtension($fileName): ?AbstractSerializer
    {
        $extension = pathinfo((string) $fileName, \PATHINFO_EXTENSION);

        /** @var AbstractSerializer $serializer */
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            if (0 === strcasecmp($extension, $serializer->getExtension())) {
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

        /** @var \DirectoryIterator $item */
        foreach (new \DirectoryIterator($extractPath) as $item) {
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

            if (null !== $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
