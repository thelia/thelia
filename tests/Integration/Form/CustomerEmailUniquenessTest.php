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
use Symfony\Component\Form\FormInterface;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Form\CustomerCreateForm;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitle;
use Thelia\Test\IntegrationTestCase;

/**
 * "This email already exists" has to mean an account exists, and nothing else.
 *
 * The checkout writes a guest row for whoever types an address, and nobody proved they
 * own it — so a guest row standing in the way of a registration would let anyone lock a
 * stranger out of the shop by ordering once on their address. It is also the address the
 * legitimate owner is most likely to try registering with, having just ordered on it.
 */
final class CustomerEmailUniquenessTest extends IntegrationTestCase
{
    private CustomerTitle $title;

    protected function setUp(): void
    {
        parent::setUp();

        $this->title = $this->createFixtureFactory()->customerTitle();
    }

    public function testAGuestRowDoesNotBlockARegistration(): void
    {
        $factory = $this->createFixtureFactory();
        $email = $this->freshEmail();
        $factory->guestCustomer($this->title, ['email' => $email]);

        $form = $this->submitRegistration($email);

        self::assertCount(
            0,
            $form->get('email')->getErrors(),
            'Ordering once as a guest must not take the address out of circulation.',
        );
    }

    public function testAnAccountStillBlocksARegistration(): void
    {
        $factory = $this->createFixtureFactory();
        $email = $this->freshEmail();
        $factory->customer($this->title, ['email' => $email]);

        $form = $this->submitRegistration($email);

        self::assertCount(1, $form->get('email')->getErrors());
        self::assertStringContainsString(
            'already exists',
            (string) $form->get('email')->getErrors()[0]->getMessage(),
        );
    }

    /**
     * The awkward pairing the fix above creates: one address, a guest row and an account.
     * The account is still what "already exists" is about.
     */
    public function testAnAccountBlocksARegistrationEvenNextToAGuestRow(): void
    {
        $factory = $this->createFixtureFactory();
        $email = $this->freshEmail();
        $factory->guestCustomer($this->title, ['email' => $email]);
        $factory->customer($this->title, ['email' => $email]);

        $form = $this->submitRegistration($email);

        self::assertCount(1, $form->get('email')->getErrors());
    }

    private function submitRegistration(string $email): FormInterface
    {
        $country = $this->shopCountry();

        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(CustomerCreateForm::class, FormType::class, [], ['csrf_protection' => false])
            ->getForm();

        $form->submit([
            'title' => (string) $this->title->getId(),
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'address1' => '1 Main Street',
            'city' => 'Springfield',
            'zipcode' => '12345',
            'country' => (string) $country->getId(),
            'state' => '',
            'email' => $email,
            'password' => 'a-chosen-password',
            'password_confirm' => 'a-chosen-password',
            'auto_login' => '0',
        ]);

        return $form;
    }

    private function shopCountry(): Country
    {
        return CountryQuery::create()->findOneByIsoalpha3('FRA')
            ?? $this->createFixtureFactory()->country();
    }

    private function freshEmail(): string
    {
        return 'uniqueness-'.bin2hex(random_bytes(8)).'@example.com';
    }
}
