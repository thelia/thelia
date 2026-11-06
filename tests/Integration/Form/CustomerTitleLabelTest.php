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

namespace Thelia\Tests\Integration\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Translation\Translator;
use Thelia\Form\AddressCreateForm;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerUpdateForm;
use Thelia\Test\IntegrationTestCase;

/**
 * The title of a customer (Mr, Mrs) has its own key: "Title" also names the heading of a product, a category or a
 * content, and a language that tells them apart (German: Anrede and Titel) could not translate both.
 */
final class CustomerTitleLabelTest extends IntegrationTestCase
{
    private ?Translator $shopTranslator = null;

    protected function tearDown(): void
    {
        if (null !== $this->shopTranslator) {
            (new \ReflectionProperty(Translator::class, 'instance'))->setValue(null, $this->shopTranslator);
        }

        parent::tearDown();
    }

    #[DataProvider('customerForms')]
    public function testTheTitleOfACustomerIsNotTranslatedAsAHeading(string $formClass): void
    {
        $this->translateTitlesApart();

        $label = $this->getService(TheliaFormFactory::class)
            ->createForm($formClass, FormType::class, [], ['csrf_protection' => false])
            ->getForm()
            ->get('title')
            ->getConfig()
            ->getOption('label');

        self::assertSame('Salutation', $label);
    }

    public function testEveryCatalogThatTranslatesTitleTranslatesTheTitleOfACustomer(): void
    {
        foreach (glob(THELIA_LIB.'Config/I18n/*.php') ?: [] as $file) {
            $catalog = require $file;

            if (isset($catalog['Title'])) {
                self::assertArrayHasKey('Customer title', $catalog, basename($file));
            }
        }
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function customerForms(): iterable
    {
        yield 'address' => [AddressCreateForm::class];
        yield 'registration' => [CustomerCreateForm::class];
        yield 'customer update' => [CustomerUpdateForm::class];
    }

    /**
     * A translator of a language where the heading and the title of a customer differ, in place of the shop's one
     * while the test runs: the forms read the translator instance.
     */
    private function translateTitlesApart(): void
    {
        $this->shopTranslator = Translator::getInstance();

        $translator = new Translator(new RequestStack());
        $translator->setLocale('zz_ZZ');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Title' => 'Heading', 'Customer title' => 'Salutation'], 'zz_ZZ', 'core');
    }
}
