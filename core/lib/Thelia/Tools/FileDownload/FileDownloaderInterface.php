<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/
namespace Thelia\Tools\FileDownload;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;

<<<<<<< HEAD
<<<<<<< HEAD
=======

>>>>>>> Define archive builders and formatters
=======
>>>>>>> Fix cs and add get method in managers
/**
 * Class FileDownloader
 * @package Thelia\Tools\FileDownload
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
interface FileDownloaderInterface
{
    /**
<<<<<<< HEAD
<<<<<<< HEAD
     * @param  string                                  $url
     * @param  string                                  $pathToStore
=======
     * @param string $url
     * @param string $pathToStore
>>>>>>> Define archive builders and formatters
=======
     * @param  string                                  $url
     * @param  string                                  $pathToStore
>>>>>>> Fix cs and add get method in managers
     * @throws \Thelia\Exception\FileNotFoundException
     * @throws \ErrorException
     * @throws \HttpUrlException
     *
     * Downloads the file $url in $pathToStore
     */
    public function download($url, $pathToStore);

    public function __construct(LoggerInterface $logger, Translator $translator);

    /**
     * @return $this
     *
     * Returns an hydrated instance
     */
    public static function getInstance();
<<<<<<< HEAD
<<<<<<< HEAD
}
=======
}
>>>>>>> Define archive builders and formatters
=======
}
>>>>>>> Fix cs and add get method in managers
