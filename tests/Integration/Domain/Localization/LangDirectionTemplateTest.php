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

namespace Thelia\Tests\Integration\Domain\Localization;

use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Lang;
use Thelia\Test\IntegrationTestCase;
use Twig\Loader\FilesystemLoader;
use TwigEngine\Template\TwigParser;

/**
 * A template writes its "dir" attribute from the lang_direction variable, which the Twig
 * parser hands to every render. No language written right to left is installed by default,
 * so the shop language is created here.
 */
final class LangDirectionTemplateTest extends IntegrationTestCase
{
    private const PROBE_TEMPLATE = 'lang_direction_probe';

    private string $templateDirectory;

    private ?Lang $previousLang = null;

    private bool $previousAdminEnv = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousAdminEnv = Request::$isAdminEnv;
        Request::$isAdminEnv = false;

        $this->previousLang = $this->session()->getLang(false);

        $this->templateDirectory = sys_get_temp_dir().'/lang_direction_'.uniqid('', true);
        mkdir($this->templateDirectory);
        file_put_contents(
            $this->templateDirectory.'/'.self::PROBE_TEMPLATE.'.html.twig',
            '<html dir="{{ lang_direction }}">',
        );

        /** @var FilesystemLoader $loader */
        $loader = static::getContainer()->get('twig.loader.native_filesystem');
        $loader->addPath($this->templateDirectory);
    }

    protected function tearDown(): void
    {
        $session = $this->session();

        $this->previousLang instanceof Lang
            ? $session->setLang($this->previousLang)
            : $session->remove('thelia.current.lang');

        Request::$isAdminEnv = $this->previousAdminEnv;

        // Lang memoizes the default language in a static that outlives the transaction
        // rollback: left as it is, the language this test made default would answer
        // every later test in the process.
        $this->forgetDefaultLanguage();

        foreach (glob($this->templateDirectory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->templateDirectory);

        parent::tearDown();
    }

    public function testATemplateRenderedInARightToLeftLanguageReadsRtl(): void
    {
        $this->session()->setLang($this->rightToLeftLang());

        self::assertSame('<html dir="rtl">', $this->render());
    }

    public function testATemplateRenderedInALeftToRightLanguageReadsLtr(): void
    {
        $this->session()->setLang($this->createFixtureFactory()->lang([
            'title' => 'French',
            'code' => 'fr',
            'locale' => 'fr_FR',
        ]));

        self::assertSame('<html dir="ltr">', $this->render());
    }

    /**
     * Nothing is rendered outside a request in practice only until a command renders an
     * email: with no language in session the direction has to fall back to the default
     * language of the shop rather than to nothing at all.
     */
    public function testWithNoLanguageInSessionTheDefaultLanguageAnswers(): void
    {
        $this->session()->remove('thelia.current.lang');

        $this->rightToLeftLang()->toggleDefault();
        $this->forgetDefaultLanguage();

        self::assertSame('<html dir="rtl">', $this->render());
    }

    private function render(): string
    {
        return $this->getService(TwigParser::class)->render(self::PROBE_TEMPLATE.'.html');
    }

    private function rightToLeftLang(): Lang
    {
        return $this->createFixtureFactory()->lang([
            'title' => 'Arabic',
            'code' => 'ar',
            'locale' => 'ar_SA',
        ]);
    }

    private function session(): Session
    {
        return static::getContainer()->get('request_stack')->getMainRequest()->getSession();
    }

    private function forgetDefaultLanguage(): void
    {
        $defaultLanguage = new \ReflectionProperty(Lang::class, 'defaultLanguage');
        $defaultLanguage->setValue(null, null);
    }
}
