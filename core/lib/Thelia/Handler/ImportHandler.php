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

namespace Thelia\Handler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Archiver\ArchiverManager;
use Thelia\Core\Event\ImportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\Core\Serializer\SerializerManager;
use Thelia\Core\Translation\Translator;
use Thelia\Files\FileConfiguration;
use Thelia\Form\Exception\FormValidationException;
use Thelia\ImportExport\Import\AbstractImport;
use Thelia\Model\Import;
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
    /**
     * Compound extensions the archivers read but do not declare. Thelia used to accept
     * them by accident, through a substring lookup on the file name, and "tar czf"
     * produces them, so they stay accepted. Each maps to the extension of the archiver
     * that reads it, so an unavailable archiver does not advertise its compound form.
     */
    public const COMPOUND_ARCHIVE_EXTENSIONS = [
        'tar.gz' => 'tgz',
        'tar.bz2' => 'bz2',
    ];

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface An event dispatcher interface
     */
    protected $eventDispatcher;

    /**
     * @var \Thelia\Core\Serializer\SerializerManager The serializer manager service
     */
    protected $serializerManager;

    /**
     * @var \Thelia\Core\Archiver\ArchiverManager The archiver manager service
     */
    protected $archiverManager;

    /**
     * Class constructor.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     *                                                                                       An event dispatcher interface
     * @param \Thelia\Core\Serializer\SerializerManager                   $serializerManager
     *                                                                                       The serializer manager service
     * @param \Thelia\Core\Archiver\ArchiverManager                       $archiverManager
     *                                                                                       The archiver manager service
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SerializerManager $serializerManager,
        ArchiverManager $archiverManager
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->serializerManager = $serializerManager;
        $this->archiverManager = $archiverManager;
    }

    /**
     * Get import model based on given identifier.
     *
     * @param int  $importId          An import identifier
     * @param bool $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return \Thelia\Model\Import|null
     */
    public function getImport($importId, $dispatchException = false)
    {
        $import = (new ImportQuery())->findPk($importId);

        if ($import === null && $dispatchException) {
            throw new \ErrorException(
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
     * Get import model based on given reference.
     *
     * @param string $importRef         An import reference
     * @param bool   $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return \Thelia\Model\Import|null
     */
    public function getImportByRef($importRef, $dispatchException = false)
    {
        $import = (new ImportQuery())->findOneByRef($importRef);

        if ($import === null && $dispatchException) {
            throw new \ErrorException(
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
     * Get import category model based on given identifier.
     *
     * @param int  $importCategoryId  An import category identifier
     * @param bool $dispatchException Dispatch exception if model doesn't exist
     *
     * @throws \ErrorException
     *
     * @return \Thelia\Model\ImportCategory|null
     */
    public function getCategory($importCategoryId, $dispatchException = false)
    {
        $category = (new ImportCategoryQuery())->findPk($importCategoryId);

        if ($category === null && $dispatchException) {
            throw new \ErrorException(
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

    /**
     * Import.
     *
     * @return \Thelia\Core\Event\ImportEvent
     */
    public function import(Import $import, File $file, Lang $language = null)
    {
        $archiver = $this->matchArchiverByExtension($file->getFilename());

        if ($archiver !== null) {
            $file = $this->extractArchive($file, $archiver);
        }

        $serializer = $this->matchSerializerByExtension($file->getFilename());

        if ($serializer === null) {
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

        /** @var \Thelia\ImportExport\Import\AbstractImport $instance */
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
     * @return array<int, string>
     */
    public function getAcceptedExtensions()
    {
        $extensions = [];

        /** @var \Thelia\Core\Serializer\AbstractSerializer $serializer */
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            $extensions[] = strtolower($serializer->getExtension());
        }

        $extensions = array_merge($extensions, $this->getAcceptedArchiveExtensions());

        return array_values(array_unique($extensions));
    }

    /**
     * Mime types matching getAcceptedExtensions(), for the file input "accept" hint.
     *
     * @return array<int, string>
     */
    public function getAcceptedMimeTypes()
    {
        $mimeTypes = [];

        /** @var \Thelia\Core\Serializer\AbstractSerializer $serializer */
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            $mimeTypes[] = $serializer->getMimeType();
        }

        /** @var \Thelia\Core\Archiver\AbstractArchiver $archiver */
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
     * @param string $fileName File name
     *
     * @throws \Thelia\Form\Exception\FormValidationException when the file may not be imported
     */
    public function validateUpload($fileName): void
    {
        $fileName = (string) $fileName;

        $dangerousExtension = FileConfiguration::findExecutableExtension($fileName);

        if ($dangerousExtension !== null) {
            throw new FormValidationException(
                Translator::getInstance()->trans(
                    'The extension "%extension" is not allowed',
                    [
                        '%extension' => $dangerousExtension,
                    ]
                )
            );
        }

        $acceptedExtensions = $this->getAcceptedExtensions();

        if ($this->matchExtension($fileName, $acceptedExtensions) === null) {
            throw new FormValidationException(
                Translator::getInstance()->trans(
                    'The extension "%extension" is not allowed. Accepted formats: %formats',
                    [
                        '%extension' => pathinfo($fileName, \PATHINFO_EXTENSION),
                        '%formats' => implode(', ', $acceptedExtensions),
                    ]
                )
            );
        }
    }

    /**
     * Match archiver relative to file name.
     *
     * @param string $fileName File name
     *
     * @return \Thelia\Core\Archiver\AbstractArchiver|null
     */
    public function matchArchiverByExtension($fileName)
    {
        $extension = $this->matchExtension((string) $fileName, $this->getAcceptedArchiveExtensions());

        if ($extension === null) {
            return null;
        }

        $extension = self::COMPOUND_ARCHIVE_EXTENSIONS[$extension] ?? $extension;

        /** @var \Thelia\Core\Archiver\AbstractArchiver $archiver */
        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            if (strcasecmp($extension, $archiver->getExtension()) === 0) {
                return $archiver;
            }
        }

        return null;
    }

    /**
     * Match serializer relative to file name.
     *
     * @param string $fileName File name
     *
     * @return \Thelia\Core\Serializer\AbstractSerializer|null
     */
    public function matchSerializerByExtension($fileName)
    {
        $extension = pathinfo((string) $fileName, \PATHINFO_EXTENSION);

        /** @var \Thelia\Core\Serializer\AbstractSerializer $serializer */
        foreach ($this->serializerManager->getSerializers() as $serializer) {
            if (strcasecmp($extension, $serializer->getExtension()) === 0) {
                return $serializer;
            }
        }

        return null;
    }

    /**
     * Extensions of the available archivers, plus the compound forms they can read
     * without declaring them.
     *
     * @return array<int, string>
     */
    protected function getAcceptedArchiveExtensions()
    {
        $extensions = [];

        /** @var \Thelia\Core\Archiver\AbstractArchiver $archiver */
        foreach ($this->archiverManager->getArchivers(true) as $archiver) {
            $extensions[] = strtolower($archiver->getExtension());
        }

        foreach (self::COMPOUND_ARCHIVE_EXTENSIONS as $compoundExtension => $archiverExtension) {
            if (\in_array($archiverExtension, $extensions, true)) {
                $extensions[] = $compoundExtension;
            }
        }

        return array_values(array_unique($extensions));
    }

    /**
     * Returns the longest of the given extensions the file name ends with, so that
     * "catalogue.tar.gz" resolves to "tar.gz" and not to "gz".
     *
     * @param array<int, string> $extensions
     */
    protected function matchExtension(string $fileName, array $extensions): ?string
    {
        $fileName = strtolower($fileName);
        $match = null;

        foreach ($extensions as $extension) {
            $extension = strtolower($extension);

            if (!str_ends_with($fileName, '.'.$extension)) {
                continue;
            }

            if ($match === null || \strlen($extension) > \strlen($match)) {
                $match = $extension;
            }
        }

        return $match;
    }

    /**
     * Extract archive.
     *
     * @return \Symfony\Component\HttpFoundation\File\File First file in unarchiver
     */
    public function extractArchive(File $file, ArchiverInterface $archiver)
    {
        $archiver->open($file->getPathname());

        $extractpath = \dirname($archiver->getArchivePath()).DS.uniqid();

        $archiver->extract($extractpath);

        /** @var \DirectoryIterator $item */
        foreach (new \DirectoryIterator($extractpath) as $item) {
            if (!$item->isDot() && $item->isFile()) {
                $file = new File($item->getPathname());

                break;
            }
        }

        return $file;
    }

    /**
     * Process import.
     *
     * @param \Thelia\ImportExport\Import\AbstractImport  $import     An import
     * @param \Thelia\Core\Serializer\SerializerInterface $serializer A serializer interface
     *
     * @return array List of errors
     */
    protected function processImport(AbstractImport $import, SerializerInterface $serializer)
    {
        $errors = [];

        $import->setData($serializer->unserialize($import->getFile()->openFile('r')));

        foreach ($import as $idx => $data) {
            $import->checkMandatoryColumns($data);

            $error = $import->importData($data);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
