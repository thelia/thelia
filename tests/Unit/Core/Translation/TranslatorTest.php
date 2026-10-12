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

namespace Thelia\Tests\Unit\Core\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Lang;

/**
 * The shop locale comes from the session language when there is one, and a
 * message is looked up in the global domain before its own.
 */
final class TranslatorTest extends TestCase
{
    private ?Translator $previousInstance;

    private bool $previousAdminEnv;

    private RequestStack $requestStack;

    private Translator $translator;

    protected function setUp(): void
    {
        $this->previousInstance = $this->instanceProperty()->getValue();
        $this->previousAdminEnv = Request::$isAdminEnv;
        Request::$isAdminEnv = false;

        $this->requestStack = new RequestStack();
        $this->translator = new Translator($this->requestStack);
        $this->translator->setLocale('en_US');
        $this->translator->addLoader('array', new ArrayLoader());
    }

    protected function tearDown(): void
    {
        $this->instanceProperty()->setValue(null, $this->previousInstance);
        Request::$isAdminEnv = $this->previousAdminEnv;
    }

    public function testTheLocaleIsTheOneOfTheSessionLanguage(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->setLang((new Lang())->setLocale('de_DE'));
        $request = new Request();
        $request->setSession($session);
        $this->requestStack->push($request);

        self::assertSame('de_DE', $this->translator->getLocale());
    }

    public function testARequestWithoutSessionKeepsTheTranslatorLocale(): void
    {
        $this->requestStack->push(new Request());

        self::assertSame('en_US', $this->translator->getLocale());
    }

    public function testWithoutAnyRequestTheTranslatorLocaleIsKept(): void
    {
        self::assertSame('en_US', $this->translator->getLocale());
    }

    public function testAMessageWithoutLocaleIsTranslatedInTheLocaleOfTheSession(): void
    {
        $this->translator->addResource('array', ['Hello' => 'Bonjour'], 'fr_FR', 'core');
        $this->translator->addResource('array', ['Hello' => 'Hello there'], 'en_US', 'core');

        $session = new Session(new MockArraySessionStorage());
        $session->setLang((new Lang())->setLocale('fr_FR'));
        $request = new Request();
        $request->setSession($session);
        $this->requestStack->push($request);

        self::assertSame('Bonjour', $this->translator->trans('Hello'));
    }

    public function testAMessageIsTranslatedInTheCoreDomainByDefault(): void
    {
        $this->translator->addResource('array', ['Hello %name%' => 'Bonjour %name%'], 'fr_FR', 'core');

        self::assertSame('Bonjour Alice', $this->translator->trans('Hello %name%', ['%name%' => 'Alice'], null, 'fr_FR'));
    }

    public function testAMessageIsTranslatedInTheDomainItNames(): void
    {
        $this->translator->addResource('array', ['Hello' => 'Bonjour du module'], 'fr_FR', 'mymodule');

        self::assertSame('Bonjour du module', $this->translator->trans('Hello', [], 'mymodule', 'fr_FR'));
    }

    public function testTheGlobalTranslationPrefixedWithTheDomainWinsByDefault(): void
    {
        $this->translator->addResource('array', ['Hello' => 'Bonjour du cœur'], 'fr_FR', 'core');
        $this->translator->addResource('array', ['Hello' => 'Bonjour global', 'core.Hello' => 'Bonjour global du cœur'], 'fr_FR', Translator::GLOBAL_FALLBACK_DOMAIN);

        self::assertSame('Bonjour global du cœur', $this->translator->trans('Hello', [], 'core', 'fr_FR'));
    }

    public function testTheGlobalTranslationOfTheBareMessageWinsOverTheDomainOne(): void
    {
        $this->translator->addResource('array', ['Hello' => 'Bonjour du cœur'], 'fr_FR', 'core');
        $this->translator->addResource('array', ['Hello' => 'Bonjour global'], 'fr_FR', Translator::GLOBAL_FALLBACK_DOMAIN);

        self::assertSame('Bonjour global', $this->translator->trans('Hello', [], 'core', 'fr_FR'));
    }

    public function testTheGlobalTranslationsAreIgnoredWithoutFallback(): void
    {
        $this->translator->addResource('array', ['Hello' => 'Bonjour du cœur'], 'fr_FR', 'core');
        $this->translator->addResource('array', ['Hello' => 'Bonjour global', 'core.Hello' => 'Bonjour global du cœur'], 'fr_FR', Translator::GLOBAL_FALLBACK_DOMAIN);

        self::assertSame('Bonjour du cœur', $this->translator->trans('Hello', [], 'core', 'fr_FR', true, false));
    }

    public function testAMissingMessageIsReturnedAsIsOrEmptyWhenAskedTo(): void
    {
        self::assertSame('Hello Alice', $this->translator->trans('Hello %name%', ['%name%' => 'Alice'], 'core', 'fr_FR'));
        self::assertSame('', $this->translator->trans('Hello %name%', ['%name%' => 'Alice'], 'core', 'fr_FR', false));
    }

    private function instanceProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(Translator::class, 'instance');
    }
}
