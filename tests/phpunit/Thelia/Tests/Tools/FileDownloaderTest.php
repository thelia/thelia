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

use Symfony\Component\DependencyInjection\Container;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Tools\FileDownload\FileDownloader;

/**
 * Class FileDownloaderTest
 * @package Thelia\Tests\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FileDownloaderTest extends \PHPUnit_Framework_TestCase
{
    /** @var  FileDownloader */
    protected $downloader;

    public function setUp()
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

    /**
     * @expectedException \Thelia\Exception\HttpUrlException
     * @expectedExceptionMessage Tried to download a file, but the URL was not valid: foo
     */
    public function testFileDownloadInvalidURL()
    {
        $this->downloader->download("foo", "bar");
    }

    /**
     * @expectedException \Thelia\Exception\FileNotFoundException
     */
    public function testFileDownloadNonExistingFile()
    {
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
