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

namespace Thelia\Tests\Core\Hook;

use Thelia\Model\ConfigQuery;
use Thelia\Model\Module;
use Thelia\Tests\WebTestCase;

/**
 * Class HookTest
 * @package Thelia\Tests\Core\Hook
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class HookTest extends WebTestCase
{
    public static $templateBackupPath;

    public static $cache_dir;

    /**
     * get the content of the test page with our test template and test module.
     * the content is used by all other test functions and saved under cache/test/hook.html
     *
     * @return mixed
     */
    public function testHome()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/',
            [],
            [],
            []
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 'Http status code must be 200');

        $content = $client->getResponse()->getContent();

        file_put_contents($this::$cache_dir . '/hook.html', $content);

        $this->assertNotFalse(strpos($content, "TEMPLATE-TEST-HOOK"));

        return $content;
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testConfigTag($content)
    {
        $this->assertStringContains($content, "main.head-top test0");
        $this->assertStringContains($content, "main.head-top test1");
        $this->assertStringContains($content, "main.head-top test2");
        // tag with active="0", should not be present
        $this->assertStringNotContains($content, "main.head-top test3");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testHookFunction($content)
    {
        $this->assertStringContains($content, "main.body-top 1-1");
        $this->assertStringContains($content, "main.body-top 1-2");
        $this->assertStringContains($content, "main.body-top 2");
        $this->assertStringBefore($content, "main.body-top 1", "main.body-top 2");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testHookIfElse($content)
    {
        $this->assertStringContains($content, "main.navbar-secondary 1");
        $this->assertStringContains($content, "::main.navbar-secondary ifhook::");
        $this->assertStringNotContains($content, "::main.navbar-secondary elsehook::");

        $this->assertStringContains($content, "::main.navbar-primary ifhook::");
        $this->assertStringNotContains($content, "::main.navbar-primary elsehook::");

        // block
        $this->assertStringNotContains($content, "::product.additional ifhook::");
        $this->assertStringContains($content, "::product.additional elsehook::");

        $this->assertStringContains($content, "::main.footer-body ifhook::");
        $this->assertStringNotContains($content, "::main.footer-body elsehook::");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testHookBlock($content)
    {
        $this->assertStringContains($content, "::main.footer-body id1 class1 content1::");
        $this->assertStringContains($content, "::main.footer-body id2 class2 content2::");
        $this->assertStringBefore($content, "::main.footer-body id1 class1 content1::", "::main.footer-body id2 class2 content2::");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookGlobal($content)
    {
        $this->assertStringContains($content, ":: main.content-top ::");
        $this->assertStringContains($content, ":: request : GET / HTTP/1.1");
        $this->assertRegExp('/:: session : [a-f0-9]{40,} ::/', $content);
        $this->assertStringContains($content, ":: cart : not null ::");
        $this->assertStringContains($content, ":: order : not null ::");
        $this->assertStringContains($content, ":: currency : 1 ::");
        $this->assertStringContains($content, ":: customer :  ::");
        $this->assertStringContains($content, ":: lang : 2 ::");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookRender($content)
    {
        $this->assertStringContains($content, ":: function render ::");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookDump($content)
    {
        $this->assertStringContains($content, ":: function dump ::");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookAddCSS($content)
    {
        $this->assertRegExp('/<link\\s+rel="stylesheet"\\s+type="text\\/css"\\s+href="http:\\/\\/localhost\\/assets\\/frontOffice\\/hooktest\\/HookTest\\/assets\\/css\\/.*\\.css"\\s*\\/>/', $content);
        $this->assertRegExp('/<link\\s+rel="stylesheet"\\s+type="text\\/css"\\s+href="http:\\/\\/localhost\\/assets\\/frontOffice\\/hooktest\\/HookTest\\/assets\\/css\\/.*\\.css"\\s+media="print"\\s*\\/>/', $content);
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookAddJS($content)
    {
        $this->assertRegExp('/<script\\s+type="text\\/javascript"\\s+src="http:\\/\\/localhost\\/assets\\/frontOffice\\/hooktest\\/HookTest\\/assets\\/js\\/.*\\.js"\\s*>/', $content);
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookTrans($content)
    {
        $this->assertStringContains($content, ":: Hodor en_US Hodor ::");
        $this->assertStringContains($content, ":: Hello en_US World ::");
        $this->assertStringContains($content, ":: Hello Hodor ::");
        $this->assertStringContains($content, ":: Salut fr_FR Hodor ::");
    }

    /**
     * @param string $content
     * @depends testHome
     */
    public function testBaseHookAssetsOverride($content)
    {
        $this->assertStringContains($content, ":: file override1 from module/default ::");
        $this->assertStringContains($content, ":: file override2 from module/hooktest ::");
        $this->assertStringContains($content, ":: file override3 from template/hooktest ::");

        // assets function
        preg_match('/asset file 1 : http:\/\/localhost\/([^\s]*)/', $content, $matches);
        $this->assertCount(2, $matches);
        $this->assertFileExists(THELIA_WEB_DIR . $matches[1]);
        $this->assertStringContains(file_get_contents(THELIA_WEB_DIR . $matches[1]), "/* style1 in module/default */");

        preg_match('/asset file 2 : http:\/\/localhost\/([^\s]*)/', $content, $matches);
        $this->assertCount(2, $matches);
        $this->assertFileExists(THELIA_WEB_DIR . $matches[1]);
        $this->assertStringContains(file_get_contents(THELIA_WEB_DIR . $matches[1]), "/* style2 in module/hooktest */");

        preg_match('/asset file 3 : http:\/\/localhost\/([^\s]*)/', $content, $matches);
        $this->assertCount(2, $matches);
        $this->assertFileExists(THELIA_WEB_DIR . $matches[1]);
        $this->assertStringContains(file_get_contents(THELIA_WEB_DIR . $matches[1]), "/* style3 in template/hooktest */");
    }

    public static function setUpBeforeClass()
    {
        self::$cache_dir = THELIA_ROOT . "cache/test";
        self::$templateBackupPath = ConfigQuery::read('active-front-template', 'default');
        ConfigQuery::write('active-front-template', 'hooktest');
    }

    public static function tearDownAfterClass()
    {
        ConfigQuery::write('active-front-template', self::$templateBackupPath);
    }

    protected function assertStringContains($data, $needle, $message = "")
    {
        $this->assertTrue((false !== strpos($data, $needle)), $message);
    }

    protected function assertStringNotContains($data, $needle, $message = "")
    {
        $this->assertTrue((false === strpos($data, $needle)), $message);
    }

    protected function assertStringBefore($data, $string1, $string2, $message = "")
    {
        $this->assertTrue((strpos($data, $string1) < strpos($data, $string2)), $message);
    }

    public function getKernel()
    {
        $kernel = $this->getMock("Symfony\Component\HttpKernel\KernelInterface");

        return $kernel;
    }

    public function getContainer()
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

        return $container;
    }

    public static function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!self::deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    public static function copyDirectory($sourceDir, $targetDir)
    {
        if (!file_exists($sourceDir)) {
            return false;
        }
        if (!is_dir($sourceDir)) {
            return copy($sourceDir, $targetDir);
        }
        if (!mkdir($targetDir)) {
            return false;
        }
        foreach (scandir($sourceDir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!self::copyDirectory($sourceDir.DIRECTORY_SEPARATOR.$item, $targetDir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }
        }

        return true;
    }
}
