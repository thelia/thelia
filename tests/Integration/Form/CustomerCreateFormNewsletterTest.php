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

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Form\CustomerCreateForm;
use Thelia\Form\CustomerProfileUpdateForm;
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * The registration form offers the newsletter box only to a shop that has a newsletter.
 */
final class CustomerCreateFormNewsletterTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTheRegistrationFormHasTheNewsletterBoxByDefault(): void
    {
        self::assertTrue($this->hasNewsletterField(CustomerCreateForm::class));
    }

    public function testTheRegistrationFormHasNoNewsletterBoxWhenTheNewsletterIsOff(): void
    {
        ConfigQuery::write('newsletter_enabled', '0');

        self::assertFalse($this->hasNewsletterField(CustomerCreateForm::class));
        self::assertFalse($this->hasNewsletterField(CustomerProfileUpdateForm::class));
    }

    public function testTheBoxIsBackWhenTheNewsletterIsSwitchedBackOn(): void
    {
        ConfigQuery::write('newsletter_enabled', '0');
        ConfigQuery::write('newsletter_enabled', '1');

        self::assertTrue($this->hasNewsletterField(CustomerCreateForm::class));
    }

    private function hasNewsletterField(string $formClass): bool
    {
        return $this->getService(TheliaFormFactory::class)
            ->createForm($formClass, FormType::class, [], ['csrf_protection' => false])
            ->getForm()
            ->has('newsletter');
    }
}
