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

use BackOfficeDefaultTwigBundle\Form\Sale\SaleType;
use BackOfficeDefaultTwigBundle\Service\Sale\SaleCustomerIdsReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;
use Thelia\Model\Sale;

final class SaleTypeTest extends TestCase
{
    /** A submission that saves a plain public sale with no countdown. */
    private const VALID_SUBMISSION = [
        'id' => '7',
        'locale' => 'fr_FR',
        'title' => 'Soldes de printemps',
        'label' => '-20%',
        'chapo' => '',
        'description' => '',
        'postscriptum' => '',
        'active' => '1',
        'display_initial_price' => '1',
        'start_date' => '2026-09-01 00:00:00',
        'end_date' => '2026-09-30 23:59:59',
        'price_offset_type' => '10',
        'audience_mode' => '0',
        'countdown_mode' => '0',
    ];

    protected function setUp(): void
    {
        // The bundle under test ships in its own package (thelia-templates/default-twig),
        // and the core CI installs the published version, not its main branch. A screen
        // this suite already covers can therefore be absent from the release the runner
        // resolves: skip, the way assertPageRenders() does for the back-office HTTP
        // tests, instead of turning the core red over a dependency it does not control.
        if (!class_exists(SaleCustomerIdsReader::class)) {
            self::markTestSkipped('SaleCustomerIdsReader is not in the published default-twig version yet.');
        }
    }

    public function testTargetingAndCountdownFieldsArePresentByDefault(): void
    {
        $form = $this->createForm();

        $this->assertTrue($form->has('audience_mode'));
        $this->assertTrue($form->has('hide_products'));
        $this->assertTrue($form->has('countdown_mode'));
    }

    public function testCustomerGroupsIsNotAnOfferedAudience(): void
    {
        $choices = $this->createForm()->get('audience_mode')->getConfig()->getOption('choices');

        $this->assertNotContains(
            Sale::AUDIENCE_MODE_CUSTOMER_GROUPS,
            array_values((array) $choices),
            'customer groups are US #122: the mode must not be offered in the back office yet',
        );
    }

    public function testAnAudienceOutsideTheOfferedChoicesIsRefused(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'audience_mode' => (string) Sale::AUDIENCE_MODE_CUSTOMER_GROUPS,
        ]));

        $this->assertFalse($form->isValid(), 'posting the customer-groups mode must not go through');
    }

    public function testLeadHoursFieldIsOnlyBuiltForTheLeadHoursMode(): void
    {
        $this->assertFalse(
            $this->createForm(['countdown_mode' => Sale::COUNTDOWN_MODE_NONE])->has('countdown_lead_hours'),
            'a sale without countdown has no lead hours to fill in',
        );
        $this->assertFalse(
            $this->createForm(['countdown_mode' => Sale::COUNTDOWN_MODE_FROM_OPENING])->has('countdown_lead_hours'),
            'a countdown shown from the opening never reads the lead hours',
        );
        $this->assertTrue(
            $this->createForm(['countdown_mode' => Sale::COUNTDOWN_MODE_LEAD_HOURS])->has('countdown_lead_hours'),
        );
    }

    public function testTargetingFieldsAreDroppedWhenTheAdminCannotViewCustomers(): void
    {
        $form = $this->createForm(null, ['can_target_customers' => false]);

        $this->assertFalse($form->has('audience_mode'), 'the audience cannot be edited without the customer list');
        $this->assertFalse($form->has('hide_products'));
        $this->assertTrue($form->has('countdown_mode'), 'the countdown holds no personal data and stays editable');
    }

    public function testACountdownWithoutAnEndDateIsRefused(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'countdown_mode' => (string) Sale::COUNTDOWN_MODE_FROM_OPENING,
            'end_date' => '',
        ]));

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('end date', (string) $form->getErrors(true, true)->current()->getMessage());
    }

    public function testLeadHoursBelowOneHourIsRefused(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'countdown_mode' => (string) Sale::COUNTDOWN_MODE_LEAD_HOURS,
            'countdown_lead_hours' => '0',
        ]));

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('hours', (string) $form->getErrors(true, true)->current()->getMessage());
    }

    public function testMissingLeadHoursIsRefusedWhenTheModeNeedsThem(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'countdown_mode' => (string) Sale::COUNTDOWN_MODE_LEAD_HOURS,
        ]));

        $this->assertTrue($form->has('countdown_lead_hours'), 'the field must be added back for the submitted mode');
        $this->assertFalse($form->isValid());
    }

    public function testLeadHoursPostedForAnotherModeAreDroppedInsteadOfRefused(): void
    {
        // The input stays in the page when the shop owner switches the mode, so the
        // browser keeps posting it. Dropping the value must not read as an extra field.
        $form = $this->createForm(['countdown_mode' => Sale::COUNTDOWN_MODE_LEAD_HOURS, 'countdown_lead_hours' => 48]);
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'countdown_mode' => (string) Sale::COUNTDOWN_MODE_FROM_OPENING,
            'countdown_lead_hours' => '48',
        ]));

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertFalse($form->has('countdown_lead_hours'));
        // The value that stays in the data array is cleared by SaleEventFactory, which
        // sends a threshold only for the mode that compares it to the end date.
        $this->assertSame(Sale::COUNTDOWN_MODE_FROM_OPENING, ((array) $form->getData())['countdown_mode']);
    }

    public function testAReservedSaleWithoutAnySelectedCustomerIsRefused(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'audience_mode' => (string) Sale::AUDIENCE_MODE_CUSTOMERS,
        ]));

        $this->assertFalse($form->isValid());
        $this->assertStringContainsString('at least one customer', (string) $form->getErrors(true, true)->current()->getMessage());
    }

    public function testAReservedSaleWithSelectedCustomersIsAccepted(): void
    {
        $form = $this->createForm(null, [], ['sale_customers' => ['3', '3', '5']]);
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'audience_mode' => (string) Sale::AUDIENCE_MODE_CUSTOMERS,
            'hide_products' => '1',
        ]));

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));

        $data = (array) $form->getData();
        $this->assertSame(Sale::AUDIENCE_MODE_CUSTOMERS, $data['audience_mode']);
        $this->assertTrue($data['hide_products']);
    }

    /**
     * Hiding the discounted products only means something for a sale that is reserved
     * for someone. The checkbox stays in the page when the shop owner switches back to
     * public, so the browser keeps posting it: stored as is, a flag ticked once would
     * come back on the day the sale is reserved again.
     */
    public function testAHiddenProductsFlagPostedForAPublicSaleIsDropped(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'audience_mode' => (string) Sale::AUDIENCE_MODE_PUBLIC,
            'hide_products' => '1',
        ]));

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));

        $data = (array) $form->getData();
        $this->assertSame(Sale::AUDIENCE_MODE_PUBLIC, $data['audience_mode']);
        $this->assertFalse($data['hide_products'], 'a public sale hides nothing from nobody');
    }

    public function testTheStoredTargetingSurvivesASaveMadeWithoutTheCustomerRight(): void
    {
        $form = $this->createForm(null, ['can_target_customers' => false]);
        // Nothing renders the radios, so the browser posts no audience at all.
        $form->submit(array_diff_key(self::VALID_SUBMISSION, ['audience_mode' => null]));

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        $this->assertArrayNotHasKey(
            'audience_mode',
            (array) $form->getData(),
            'no audience in the payload is what tells the event factory to replay the stored one',
        );
    }

    public function testAValidCountdownSubmissionNormalizesItsValues(): void
    {
        $form = $this->createForm();
        $form->submit(array_merge(self::VALID_SUBMISSION, [
            'countdown_mode' => (string) Sale::COUNTDOWN_MODE_LEAD_HOURS,
            'countdown_lead_hours' => '12',
        ]));

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true, false));

        $data = (array) $form->getData();
        $this->assertSame(Sale::COUNTDOWN_MODE_LEAD_HOURS, $data['countdown_mode']);
        $this->assertSame(12, $data['countdown_lead_hours']);
        $this->assertSame(Sale::AUDIENCE_MODE_PUBLIC, $data['audience_mode']);
        $this->assertFalse($data['hide_products']);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>      $options
     * @param array<string, mixed>      $post    the request payload carrying the unmapped `sale_customers[]`
     */
    private function createForm(?array $data = null, array $options = [], array $post = []): FormInterface
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], $post));

        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new SaleType(new IdentityTranslator(), new SaleCustomerIdsReader($requestStack)))
            ->getFormFactory();

        return $factory->createNamed('thelia_sale', SaleType::class, $data, $options);
    }
}
