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

namespace Thelia\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Tools\FileDownload\FileDownloader;

/**
 * Class FileDownloaderTest
 * @package Thelia\Tests\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FileDownloaderTest extends TestCase
{
    /** @var  FileDownloader */
    protected $downloader;

    public function setUp(): void
    {
        $logger = Tlog::getNewInstance();
        $translator = new Translator(
            new Container()
        );

        $this->downloader = new FileDownloader(
            $logger,
            $translator
        );
    }

    public function testFileDownloadInvalidURL()
    {
        $this->expectException(\Thelia\Exception\HttpUrlException::class);
        $this->expectExceptionMessage("Tried to download a file, but the URL was not valid: foo");
        $this->downloader->download("foo", "bar");
    }

    public function testFileDownloadNonExistingFile()
    {
        $this->expectException(\Thelia\Exception\FileNotFoundException::class);
        $this->downloader->download("https://github.com/foo/bar/baz", "baz");
    }
    
    public function testFileDownloadSuccess()
    {
        $this->downloader->download("https://github.com/", "php://temp");
    }
    
    public function testFileDownloadSuccessWithRedirect()
    {
        $this->downloader->download("https://github.com/", "php://temp");
    }
}
