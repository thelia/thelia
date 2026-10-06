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

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Event\File\FileCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\File\FileConfiguration;
use Thelia\Core\File\FileManager;
use Thelia\Core\File\SvgSanitizer;
use Thelia\Model\Lang;

readonly class FileProcessorService
{
    public function __construct(
        private FileManager $fileManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, list<string>>|null $validMimeTypes accepted mime types, mapped to the extensions they may carry.
     *                                                         Null applies the shop policy for $objectType.
     * @param list<string>|null                $extBlackList   refused extensions. Null applies the shop policy for $objectType.
     *
     * @throws ProcessFileException If file processing fails
     */
    public function processFile(
        EventDispatcherInterface $eventDispatcher,
        UploadedFile $fileBeingUploaded,
        int $parentId,
        string $parentType,
        string $objectType,
        ?array $validMimeTypes = null,
        ?array $extBlackList = null,
        string $moduleRight = 'thelia',
    ): FileCreateOrUpdateEvent {
        $this->validateUpload($fileBeingUploaded, $objectType, $validMimeTypes, $extBlackList);
        $this->sanitizeUpload($fileBeingUploaded);

        $fileModel = $this->fileManager->getModelInstance($objectType, $parentType);

        $parentModel = $fileModel->getParentFileModel();

        $defaultTitle = $parentModel->getTitle();

        if (empty($defaultTitle) && 'image' !== $objectType) {
            $defaultTitle = $fileBeingUploaded->getClientOriginalName();
        }

        $fileModel
            ->setParentId($parentId)
            ->setLocale(Lang::getDefaultLanguage()->getLocale())
            ->setTitle($defaultTitle);

        $fileCreateOrUpdateEvent = new FileCreateOrUpdateEvent($parentId);
        $fileCreateOrUpdateEvent->setModel($fileModel);
        $fileCreateOrUpdateEvent->setUploadedFile($fileBeingUploaded);
        $fileCreateOrUpdateEvent->setParentName($parentModel->getTitle());

        // Dispatch Event to the Action
        $eventDispatcher->dispatch(
            $fileCreateOrUpdateEvent,
            TheliaEvents::IMAGE_SAVE,
        );

        return $fileCreateOrUpdateEvent;
    }

    /**
     * Checks an uploaded file against the upload policy before anything is written.
     *
     * Callers that have their own policy pass it explicitly; the others get the shop
     * policy for $objectType (see FileConfiguration). Whatever the policy says, a file
     * name carrying a server-executable segment is always refused.
     *
     * Storage does not keep the name the client sent: FileManager::renameFile() drops
     * every character outside [a-zA-Z0-9-_.], so "report.php " is stored as
     * "report-1.php". The extension checks hold for both names.
     *
     * @param array<string, list<string>>|null $validMimeTypes
     * @param list<string>|null                $extBlackList
     *
     * @throws ProcessFileException when the file may not be uploaded
     */
    public function validateUpload(
        UploadedFile $fileBeingUploaded,
        string $objectType,
        ?array $validMimeTypes = null,
        ?array $extBlackList = null,
    ): void {
        // Validate if file is too big
        if (\UPLOAD_ERR_INI_SIZE === $fileBeingUploaded->getError()) {
            $message = $this->translator->trans(
                'File is too large, please retry with a file having a size less than %size%.',
                ['%size%' => \ini_get('upload_max_filesize')],
                'core',
            );

            throw new ProcessFileException($message, 403);
        }

        $policy = FileConfiguration::getConfig($objectType);
        $validMimeTypes ??= $policy['validMimeTypes'];
        $extBlackList ??= $policy['extBlackList'];

        $message = null;
        $realFileName = $fileBeingUploaded->getClientOriginalName();

        if ([] !== $validMimeTypes) {
            $mimeType = $fileBeingUploaded->getMimeType();

            if (!isset($validMimeTypes[$mimeType])) {
                $message = $this->translator->trans(
                    'Only files having the following mime type are allowed: %types%',
                    ['%types%' => implode(', ', array_keys($validMimeTypes))],
                );
            } elseif ([] !== $validMimeTypes[$mimeType]) {
                $regex = '#^(.+)\\.('.implode('|', $validMimeTypes[$mimeType]).')$#i';

                if (!preg_match($regex, $realFileName)) {
                    $message = $this->translator->trans(
                        "There's a conflict between your file extension \"%ext\" and the mime type \"%mime\"",
                        [
                            '%mime' => $mimeType,
                            '%ext' => $fileBeingUploaded->getClientOriginalExtension(),
                        ],
                    );
                }
            }
        }

        if (null === $message && null !== ($refusedExtension = $this->findRefusedExtension($fileBeingUploaded, $extBlackList))) {
            $message = $this->translator->trans(
                'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                [
                    '%extension' => $refusedExtension,
                ],
            );
        }

        // A document is served from the shop origin: one a browser opens as a page or runs
        // as a script is refused, whatever the caller's configuration.
        if (null === $message && 'document' === $objectType && null !== ($activeExtension = FileConfiguration::findBrowserActiveExtension($realFileName))) {
            $message = $this->translator->trans(
                'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                [
                    '%extension' => $activeExtension,
                ],
            );
        }

        if (null !== $message) {
            throw new ProcessFileException($message, 415);
        }
    }

    /**
     * The first extension the policy refuses, in the name the client sent or in the one
     * the file is stored under, or null when both names are allowed.
     *
     * @param list<string> $extBlackList
     */
    private function findRefusedExtension(UploadedFile $fileBeingUploaded, array $extBlackList): ?string
    {
        $blackListRegex = [] === $extBlackList ? null : '#^(.+)\\.('.implode('|', $extBlackList).')$#i';

        foreach ($this->fileNames($fileBeingUploaded) as $fileName) {
            if (null !== $blackListRegex && preg_match($blackListRegex, $fileName, $matches)) {
                return $matches[2];
            }

            // Defense in depth against double-extension bypasses (e.g. "shell.php.jpg"):
            // reject any file whose name contains a server-executable segment, not just the
            // terminal one. Applies to every upload, regardless of the caller's configuration.
            $executableExtension = FileConfiguration::findExecutableExtension($fileName);

            if (null !== $executableExtension) {
                return $executableExtension;
            }
        }

        return null;
    }

    /**
     * The name the client sent, and the one storage will give the file.
     *
     * @return list<string>
     */
    private function fileNames(UploadedFile $fileBeingUploaded): array
    {
        return array_values(array_unique([
            $fileBeingUploaded->getClientOriginalName(),
            // The model id only adds digits before the extension: 0 stands for the id to come.
            $this->fileManager->renameFile(0, $fileBeingUploaded),
        ]));
    }

    /**
     * Uploaded SVG files are served from the shop origin: strip any active content
     * so they cannot be used for stored XSS (CWE-79). Other files are left untouched.
     *
     * @throws ProcessFileException when the file is declared as SVG but cannot be parsed
     */
    public function sanitizeUpload(UploadedFile $fileBeingUploaded): void
    {
        $this->sanitizeSvgUpload($fileBeingUploaded);
    }

    /**
     * Removes the active content of an uploaded SVG (see SvgSanitizer), rewriting the
     * temporary file in place.
     *
     * @throws ProcessFileException when the file is declared as SVG but is not an SVG document
     */
    private function sanitizeSvgUpload(UploadedFile $file): void
    {
        $isSvg = SvgSanitizer::isSvg($file->getClientOriginalName(), $file->getMimeType())
            || SvgSanitizer::isSvg($this->fileManager->renameFile(0, $file));

        if (!$isSvg) {
            return;
        }

        $path = $file->getPathname();
        $content = @file_get_contents($path);

        if (false === $content || '' === $content) {
            return;
        }

        $sanitized = (new SvgSanitizer())->sanitize($content);

        if (null === $sanitized) {
            throw new ProcessFileException($this->translator->trans('The uploaded SVG file is not a valid image.'), 415);
        }

        if (false === @file_put_contents($path, $sanitized)) {
            throw new ProcessFileException($this->translator->trans('The uploaded SVG file is not a valid image.'), 415);
        }
    }
}
