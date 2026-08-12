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

        if (null === $message && [] !== $extBlackList) {
            $regex = '#^(.+)\\.('.implode('|', $extBlackList).')$#i';

            if (preg_match($regex, $realFileName)) {
                $message = $this->translator->trans(
                    'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                    [
                        '%extension' => $fileBeingUploaded->getClientOriginalExtension(),
                    ],
                );
            }
        }

        // Defense in depth against double-extension bypasses (e.g. "shell.php.jpg"):
        // reject any file whose name contains a server-executable segment, not just the
        // terminal one. Applies to every upload, regardless of the caller's configuration.
        if (null === $message && null !== ($dangerousExtension = FileConfiguration::findExecutableExtension($realFileName))) {
            $message = $this->translator->trans(
                'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                [
                    '%extension' => $dangerousExtension,
                ],
            );
        }

        if (null !== $message) {
            throw new ProcessFileException($message, 415);
        }
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
     * Removes active content (scripts, event handlers, javascript:/data: URIs and
     * foreignObject nodes) from an uploaded SVG, rewriting the temporary file in place.
     *
     * @throws ProcessFileException when the file is declared as SVG but cannot be parsed
     */
    private function sanitizeSvgUpload(UploadedFile $file): void
    {
        $isSvg = 'svg' === strtolower($file->getClientOriginalExtension())
            || 'image/svg+xml' === $file->getMimeType();

        if (!$isSvg) {
            return;
        }

        $path = $file->getPathname();
        $content = @file_get_contents($path);

        if (false === $content || '' === $content) {
            return;
        }

        $previousErrorState = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        // LIBXML_NONET blocks network access; external entities stay disabled (no XXE).
        $loaded = $document->loadXML($content, \LIBXML_NONET);
        libxml_use_internal_errors($previousErrorState);

        if (!$loaded) {
            throw new ProcessFileException($this->translator->trans('The uploaded SVG file is not a valid image.'), 415);
        }

        $xpath = new \DOMXPath($document);

        foreach (['//*[local-name()="script"]', '//*[local-name()="foreignObject"]'] as $query) {
            $nodes = $xpath->query($query);

            if (false !== $nodes) {
                foreach (iterator_to_array($nodes) as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        $attributes = $xpath->query('//@*');

        if (false !== $attributes) {
            foreach (iterator_to_array($attributes) as $attribute) {
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }

                $name = strtolower($attribute->nodeName);
                $value = strtolower((string) preg_replace('/\s+/', '', (string) $attribute->nodeValue));

                $isEventHandler = str_starts_with($name, 'on');
                $isActiveUri = \in_array($name, ['href', 'xlink:href', 'from', 'to', 'values', 'begin'], true)
                    && (str_starts_with($value, 'javascript:') || str_starts_with($value, 'data:'));

                if ($isEventHandler || $isActiveUri) {
                    $attribute->ownerElement?->removeAttributeNode($attribute);
                }
            }
        }

        $sanitized = $document->saveXML();

        if (false !== $sanitized) {
            @file_put_contents($path, $sanitized);
        }
    }
}
