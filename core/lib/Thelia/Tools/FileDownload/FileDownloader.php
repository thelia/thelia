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
namespace Thelia\Tools\FileDownload;

use ErrorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;
use Thelia\Core\Translation\Translator as TheliaTranslator;
use Thelia\Exception\FileNotFoundException;
use Thelia\Exception\HttpUrlException;
use Thelia\Log\Tlog;
use Thelia\Tools\URL;

/**
 * Class FileDownloader.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FileDownloader implements FileDownloaderInterface
{
    public function __construct(protected LoggerInterface $logger, protected Translator $translator)
    {
    }

    public static function getInstance(): static
    {
        return new static(Tlog::getInstance(), TheliaTranslator::getInstance());
    }

    /**
     * @param string $url
     * @param string $pathToStore
     *
     * @throws FileNotFoundException
     * @throws ErrorException
     * @throws \HttpUrlException
     *
     * Downloads the file $url in $pathToStore
     */
    public function download($url, $pathToStore): void
    {
        if (!URL::checkUrl($url)) {
            /*
             * The URL is not valid
             */
            throw new HttpUrlException(
                $this->translator->trans(
                    'Tried to download a file, but the URL was not valid: %url',
                    [
                        '%url' => $url,
                    ]
                )
            );
        }

        /**
         * Try to get the file if it is online.
         */
        $con = curl_init($url);
        curl_setopt($con, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, \CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($con);
        $errno = curl_errno($con);
        $curlErrorMessage = curl_error($con);

        $httpCode = curl_getinfo($con, \CURLINFO_HTTP_CODE);

        curl_close($con);

        if (false === $response || $errno !== 0
            || ($httpCode != '200' && $httpCode != '204')
        ) {
            /**
             * The server is down ? The file doesn't exist ? Anything else ?
             */
            $errorMessage = $this->translator->trans(
                'cURL errno %errno, http code %http_code on link "%path": %error',
                [
                    '%errno' => $errno,
                    '%path' => $url,
                    '%error' => $curlErrorMessage,
                    '%http_code' => $httpCode,
                ]
            );

            $this->logger
                ->error($errorMessage)
            ;

            throw new FileNotFoundException($errorMessage);
        }

        /*
         * Inform that you've downloaded a file
         */
        $this->logger
            ->info(
                $this->translator->trans(
                    'The file %path has been successfully downloaded',
                    [
                        '%path' => $url,
                    ]
                )
            )
        ;

        /**
         * Then try to write it on the disk.
         */
        $file = @fopen($pathToStore, 'w');

        if ($file === false) {
            $translatedErrorMessage = $this->translator->trans(
                'Failed to open a writing stream on the file: %file',
                [
                    '%file' => $pathToStore,
                ]
            );

            $this->logger->error($translatedErrorMessage);
            throw new ErrorException($translatedErrorMessage);
        }

        fwrite($file, $response);
        fclose($file);
    }
}
