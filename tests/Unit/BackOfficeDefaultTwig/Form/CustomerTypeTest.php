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

namespace Thelia\Tests\Unit\BackOfficeDefaultTwig\Form;

use BackOfficeDefaultTwigBundle\Form\Customer\CustomerType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;

final class CustomerTypeTest extends TestCase
{
    private const ADDRESS_FIELDS = [
        'address1', 'address2', 'address3', 'zipcode', 'city',
        'country', 'state', 'phone', 'cellphone', 'company',
    ];

    private const IDENTITY_FIELDS = ['title', 'firstname', 'lastname', 'email', 'lang_id', 'discount', 'reseller'];

    public function testAddressFieldsArePresentByDefault(): void
    {
        $form = $this->createForm([]);

        foreach (self::ADDRESS_FIELDS as $field) {
            $this->assertTrue($form->has($field), \sprintf('field "%s" is expected on the default form', $field));
        }
    }

    public function testAddressFieldsAreDroppedWhenTheCustomerHasNoAddress(): void
    {
        $form = $this->createForm(['include_address' => false]);

        foreach (self::ADDRESS_FIELDS as $field) {
            $this->assertFalse($form->has($field), \sprintf('field "%s" must not be built without an address', $field));
        }
    }

    public function testIdentityFieldsSurviveWithoutAddress(): void
    {
        $form = $this->createForm(['include_address' => false]);

        foreach (self::IDENTITY_FIELDS as $field) {
            $this->assertTrue($form->has($field), \sprintf('field "%s" belongs to the customer, not to its address', $field));
        }
    }

    public function testFormWithoutAddressValidatesOnIdentityAlone(): void
    {
        $form = $this->createForm(['include_address' => false]);
        $form->submit([
            'title' => '1',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
            'lang_id' => '',
            'discount' => '',
            'reseller' => null,
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
    }

    public function testFormWithAddressStillRequiresTheAddressFields(): void
    {
        $form = $this->createForm([]);
        $form->submit([
            'title' => '1',
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
        ]);

        $this->assertFalse($form->isValid(), 'the default form must keep its address constraints');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createForm(array $options): FormInterface
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new CustomerType(new IdentityTranslator()))
            ->getFormFactory();

        return $factory->createNamed('thelia_customer_update', CustomerType::class, null, array_merge([
            'title_choices' => ['Mr' => 1],
            'country_choices' => ['France' => 64],
            'lang_choices' => ['Français' => 1],
        ], $options));
    }
}
