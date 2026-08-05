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

namespace Thelia\Tests\Integration\Flexy;

use FlexyBundle\Form\CustomerUpdateForm;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Test\IntegrationTestCase;

/**
 * The account form of the Flexy theme must never report a violation on the email
 * address when the shop forbids email changes: the field is disabled, so Symfony
 * ignores what it submits and the customer has no way to fix such an error.
 *
 * The controller feeds the current customer values as initial data, which is what
 * keeps the address visible when the form is redisplayed after another field failed.
 */
final class CustomerUpdateFormTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(CustomerUpdateForm::class)) {
            self::markTestSkipped('The Flexy front-office theme is not installed.');
        }

        $registeredForms = static::getContainer()->getParameter('Thelia.parser.forms');
        if (!\is_array($registeredForms) || !isset($registeredForms[CustomerUpdateForm::FORM_NAME])) {
            self::markTestSkipped('The Flexy bundle is not registered in config/bundles.php.');
        }

        $factory = $this->createFixtureFactory();
        $this->customer = $factory->customer($factory->customerTitle());

        $this->getService(SecurityContext::class)->setCustomerUser($this->customer);
    }

    protected function tearDown(): void
    {
        // ConfigQuery keeps a static cache that outlives the transaction rollback.
        ConfigQuery::write('customer_change_email', 0);

        parent::tearDown();
    }

    public function testLockedEmailKeepsItsValueAndReportsNoViolation(): void
    {
        $form = $this->buildForm();

        // An empty first name makes the form invalid for a reason unrelated to the email.
        $form->submit([
            'firstname' => '',
            'lastname' => 'Doe',
            'email' => 'someone-else@test.com',
        ]);

        self::assertFalse($form->isValid());
        self::assertSame($this->customer->getEmail(), $form->get('email')->getData());
        self::assertCount(0, $form->get('email')->getErrors());
    }

    public function testKeepingTheirOwnEmailIsNotADuplicate(): void
    {
        ConfigQuery::write('customer_change_email', 1);

        $form = $this->buildForm();

        $form->submit([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => $this->customer->getEmail(),
        ]);

        self::assertCount(0, $form->get('email')->getErrors());
        self::assertTrue($form->isValid());
    }

    public function testEmailOwnedByAnotherCustomerIsRejected(): void
    {
        ConfigQuery::write('customer_change_email', 1);

        $factory = $this->createFixtureFactory();
        $other = $factory->customer($factory->customerTitle());

        $form = $this->buildForm();

        $form->submit([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => $other->getEmail(),
        ]);

        self::assertCount(1, $form->get('email')->getErrors());
        self::assertFalse($form->isValid());
    }

    private function buildForm(): FormInterface
    {
        return $this->getService(TheliaFormFactory::class)
            ->createForm(
                CustomerUpdateForm::FORM_NAME,
                FormType::class,
                [
                    'firstname' => $this->customer->getFirstname(),
                    'lastname' => $this->customer->getLastname(),
                    'email' => $this->customer->getEmail(),
                ],
                ['csrf_protection' => false],
            )
            ->getForm();
    }
}
