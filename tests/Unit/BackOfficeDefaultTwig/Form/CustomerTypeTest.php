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
use BackOfficeDefaultTwigBundle\Service\CustomerGroup\PostedCustomerGroupIds;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

final class CustomerTypeTest extends TestCase
{
    private const ADDRESS_FIELDS = [
        'address1', 'address2', 'address3', 'zipcode', 'city',
        'country', 'state', 'phone', 'cellphone', 'company',
    ];

    private const IDENTITY_FIELDS = ['title', 'firstname', 'lastname', 'email', 'lang_id', 'discount', 'reseller'];

    protected function setUp(): void
    {
        // The core and the back-office theme ship in separate releases: a checkout of the
        // core runs with a theme that may not carry the customer group field yet.
        if (!class_exists(PostedCustomerGroupIds::class)) {
            self::markTestSkipped('The back-office theme installed predates the customer groups.');
        }
    }

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

    public function testAddressFieldsReAddedByLegacyModuleListenersAreDropped(): void
    {
        // Reproduces ForcePhone: a FORM_AFTER_BUILD listener removes then re-adds
        // cellphone as required, on the builder, after CustomerType::buildForm.
        $builder = $this->formFactory()->createNamedBuilder('thelia_customer_update', CustomerType::class, null, [
            'title_choices' => ['Mr' => 1],
            'country_choices' => ['France' => 64],
            'lang_choices' => ['Français' => 1],
            'group_choices' => ['Default' => 1],
            'include_address' => false,
        ]);
        $builder->add('cellphone', TextType::class, ['constraints' => [new NotBlank()], 'required' => true]);

        $form = $builder->getForm();

        $this->assertFalse($form->has('cellphone'), 'a re-added address field would block the save with no address to store it on');
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

    public function testGroupFieldIsPresentAndRequiredOnEdit(): void
    {
        $form = $this->createForm(['include_id' => true, 'include_address' => false]);

        $this->assertTrue($form->has('customer_group_id'));
        $this->assertTrue($form->get('customer_group_id')->getConfig()->getRequired());
        $this->assertNull($form->get('customer_group_id')->getConfig()->getOption('placeholder'), 'an existing account always holds a group: no empty choice');

        $form->submit([
            'title' => '1', 'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com',
            'id' => '5', 'customer_group_id' => '',
        ]);
        $this->assertFalse($form->get('customer_group_id')->isValid(), 'an edit without a group must be refused');
    }

    public function testGroupFieldIsOptionalOnCreation(): void
    {
        $form = $this->createForm([]);

        $this->assertFalse($form->get('customer_group_id')->getConfig()->getRequired());
        $this->assertNotNull($form->get('customer_group_id')->getConfig()->getOption('placeholder'), 'left empty, the core files the account under the default group');
    }

    private function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new CustomerType(new IdentityTranslator()))
            ->getFormFactory();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createForm(array $options): FormInterface
    {
        return $this->formFactory()->createNamed('thelia_customer_update', CustomerType::class, null, array_merge([
            'title_choices' => ['Mr' => 1],
            'country_choices' => ['France' => 64],
            'lang_choices' => ['Français' => 1],
            'group_choices' => ['Default' => 1, 'Wholesale' => 2],
        ], $options));
    }
}
