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

namespace Thelia\Tests\Tools;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Tools\FileDownload\FileDownloader;

/**
 * Class FileDownloaderTest.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FileDownloaderTest extends TestCase
{
    /** @var FileDownloader */
    protected $downloader;

    public function setUp(): void
    {
        $logger = Tlog::getNewInstance();

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $translator = new Translator($requestStack);

        $this->downloader = new FileDownloader(
            $logger,
            $translator
        );
    }

    public function testFileDownloadInvalidURL(): void
    {
        $this->expectException(\Thelia\Exception\HttpUrlException::class);
        $this->expectExceptionMessage('Tried to download a file, but the URL was not valid: foo');
        $this->downloader->download('foo', 'bar');
    }

    public function testFileDownloadNonExistingFile(): void
    {
        $this->expectException(\Thelia\Exception\FileNotFoundException::class);
        $this->downloader->download('https://github.com/foo/bar/baz', 'baz');
    }

    public function testFileDownloadSuccess(): void
    {
        $this->downloader->download('https://github.com/', 'php://temp');
    }

    public function testFileDownloadSuccessWithRedirect(): void
    {
        $this->downloader->download('https://github.com/', 'php://temp');
    }
}
