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

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
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
use Thelia\Domain\DataTransfer\Exception\HandlerUnavailableException;
use Thelia\Domain\DataTransfer\Exception\JobRefusedException;
use Thelia\Domain\DataTransfer\Exception\UploadRefusedException;
use Thelia\Domain\DataTransfer\Import\AbstractImport;
use Thelia\Log\Tlog;
use Thelia\Messenger\JobFailureMessage;
use Thelia\Model\Import;
use Thelia\Model\ImportCategory;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Model\ImportQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ImportTableMap;

/**
 * Class ImportHandler.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportHandler
{
    /** Told the rows read while import() runs: processImport() keeps its signature. */
    private ?\Closure $onProgress = null;

    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected SerializerManager $serializerManager,
        protected ArchiverManager $archiverManager,
        protected ArchiveInspector $archiveInspector = new ArchiveInspector(),
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

    /**
     * @param (\Closure(int): void)|null $onProgress told the number of rows read, every
     *                                               DataTransferProgress::STEP rows and once at the end
     */
    public function import(Import $import, File $file, ?Lang $language = null, ?\Closure $onProgress = null): ImportEvent
    {
        $archiver = $this->matchArchiverByExtension($file->getFilename());
        $extractedDirectory = null;

        if ($archiver instanceof AbstractArchiver) {
            $this->archiveInspector->assertExtractable($file->getPathname(), $archiver->getExtension());
            $extractedDirectory = $file->getPath().DS.uniqid('', true);
        }

        // The extracted copy is only read here: it goes once the import is over,
        // whatever came of it, rather than piling up next to the uploads.
        try {
            if (null !== $extractedDirectory && $archiver instanceof AbstractArchiver) {
                $file = $this->extractInto($file, $archiver, $extractedDirectory);
            }

            return $this->importFile($import, $file, $language, $onProgress);
        } finally {
            if (null !== $extractedDirectory) {
                (new Filesystem())->remove($extractedDirectory);
            }
        }
    }

    private function importFile(Import $import, File $file, ?Lang $language, ?\Closure $onProgress): ImportEvent
    {
        $serializer = $this->matchSerializerByExtension($file->getFilename());

        if (!$serializer instanceof AbstractSerializer) {
            throw new UploadRefusedException(Translator::getInstance()->trans('The extension "%extension" is not allowed', ['%extension' => pathinfo($file->getFilename(), \PATHINFO_EXTENSION)]));
        }

        if (!$import->isHandlerAvailable()) {
            throw new HandlerUnavailableException(Translator::getInstance()->trans('The import "%ref" cannot be run: its handler class "%class" is not available. The module that provided it has probably been removed.', ['%ref' => $import->getRef(), '%class' => $import->getHandleClass()]));
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

        $this->onProgress = $onProgress;

        try {
            $errors = $this->processImport($event->getImport(), $event->getSerializer());
        } finally {
            $this->onProgress = null;
        }

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
     * Given the file, its content is checked too: the name is chosen by whoever
     * uploads it, the content is what gets stored and read.
     *
     * @throws UploadRefusedException when the file may not be imported (a FormValidationException)
     */
    public function validateUpload(string $fileName, ?File $file = null): void
    {
        $dangerousExtension = FileConfiguration::findExecutableExtension($fileName);

        if (null !== $dangerousExtension) {
            throw new UploadRefusedException(Translator::getInstance()->trans('The extension "%extension" is not allowed', ['%extension' => $dangerousExtension]));
        }

        $extension = strtolower(pathinfo($fileName, \PATHINFO_EXTENSION));
        $acceptedExtensions = $this->getAcceptedExtensions();

        if (!\in_array($extension, $acceptedExtensions, true)) {
            throw new UploadRefusedException(Translator::getInstance()->trans('The extension "%extension" is not allowed. Accepted formats: %formats', ['%extension' => $extension, '%formats' => implode(', ', $acceptedExtensions)]));
        }

        if (null === $file) {
            return;
        }

        if (!$this->contentMatchesExtension($file, $fileName)) {
            throw new UploadRefusedException(Translator::getInstance()->trans('The content of the file is not a "%extension" file.', ['%extension' => $extension]));
        }

        if ($this->matchArchiverByExtension($fileName) instanceof AbstractArchiver) {
            $this->archiveInspector->assertExtractable($file->getPathname(), $extension);
        }
    }

    /**
     * An archive must be an archive of its kind; anything else must be text, the only
     * thing a serializer reads.
     */
    private function contentMatchesExtension(File $file, string $fileName): bool
    {
        $detected = (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname());

        if (false === $detected) {
            return false;
        }

        $archiver = $this->matchArchiverByExtension($fileName);

        if ($archiver instanceof AbstractArchiver) {
            return self::withoutVendorPrefix($detected) === self::withoutVendorPrefix($archiver->getMimeType());
        }

        return str_starts_with($detected, 'text/')
            || \in_array($detected, ['application/json', 'application/xml', 'application/csv', 'application/x-empty', 'inode/x-empty'], true);
    }

    /**
     * application/x-gzip and application/gzip name the same format.
     */
    private static function withoutVendorPrefix(string $mimeType): string
    {
        return str_replace('/x-', '/', strtolower($mimeType));
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
        return $this->extractInto($file, $archiver, \dirname($file->getPathname()).DS.uniqid('', true));
    }

    /**
     * Extracts the archive into $extractPath and gives its first file at the root, or
     * the archive itself when there is none.
     */
    private function extractInto(File $file, ArchiverInterface $archiver, string $extractPath): File
    {
        $archiver->open($file->getPathname());
        $archiver->extract($extractPath);

        // An archive with nothing in it creates no folder.
        if (!is_dir($extractPath)) {
            return $file;
        }

        // The sizes an archive declares are written by whoever made it: what was
        // really written is measured, and too much is refused (the folder is then
        // removed with the rest of the extraction).
        $this->archiveInspector->assertExtractedSize($extractPath);

        /** @var \DirectoryIterator $item */
        foreach (new \DirectoryIterator($extractPath) as $item) {
            if (!$item->isDot() && $item->isFile()) {
                $file = new File($item->getPathname());

                break;
            }
        }

        return $file;
    }

    /**
     * Tells the progress callback given to import(), if any, the number of rows read
     * every DataTransferProgress::STEP rows and once at the end.
     */
    protected function processImport(AbstractImport $import, SerializerInterface $serializer): array
    {
        $onProgress = $this->onProgress;
        $errors = [];
        $read = 0;

        // A file that does not parse (broken JSON, XML or YAML) is the administrator's to
        // fix: said as such, not as a server error.
        try {
            $data = $serializer->unserialize($import->getFile()->openFile('r'));
        } catch (\Throwable $unreadable) {
            Tlog::getInstance()->addWarning(\sprintf('An imported %s file could not be read: %s', $serializer->getExtension(), JobFailureMessage::forLog($unreadable)));

            throw new UploadRefusedException(Translator::getInstance()->trans('The file cannot be read as a "%format" file: check its content.', ['%format' => $serializer->getExtension()]), 0, $unreadable);
        }

        $import->setData($data);

        $connection = Propel::getWriteConnection(ImportTableMap::DATABASE_NAME);
        $row = 0;

        foreach ($import as $data) {
            ++$row;
            $import->checkMandatoryColumns($data);

            $error = $import->importData($data);

            // A row whose save failed inside the import's transaction leaves it unable to
            // commit, whatever the import did with the exception: said at that row, with
            // nothing imported, rather than as a server error at the end.
            if ($connection instanceof ConnectionWrapper && $connection->isInTransaction() && !$connection->isCommitable()) {
                throw new JobRefusedException(Translator::getInstance()->trans('Row %row could not be saved: nothing was imported.', ['%row' => $row]));
            }

            if (null !== $error) {
                $errors[] = $error;
            }

            if (null !== $onProgress && 0 === ++$read % DataTransferProgress::STEP) {
                $onProgress($read);
            }
        }

        if (null !== $onProgress) {
            $onProgress($read);
        }

        return $errors;
    }
}
