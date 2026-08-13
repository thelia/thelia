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
use Thelia\Form\AddressCreateForm;
use Thelia\Model\Country;
use Thelia\Model\CustomerTitle;
use Thelia\Model\LangQuery;
use Thelia\Model\State;
use Thelia\Test\IntegrationTestCase;

/**
 * `country.has_states` says a state is mandatory, not that the country carries any.
 *
 * The states a customer may pick come from the state rows attached to the country,
 * so a country can offer states without forcing one (France and its departments),
 * and a country flagged as requiring one may hold none yet.
 */
final class AddressStateValidationTest extends IntegrationTestCase
{
    private CustomerTitle $title;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->title = $this->createFixtureFactory()->customerTitle();
    }

    public function testAStateFromAnotherCountryIsRejectedEvenWhenTheStateIsOptional(): void
    {
        $optional = $this->country(required: false);
        $state = $this->state($optional);
        $elsewhere = $this->country(required: false);

        // The customer picked the state, then switched the country in the same submit.
        $form = $this->submit($optional, [
            'country' => (string) $elsewhere->getId(),
            'state' => (string) $state->getId(),
        ]);

        self::assertCount(1, $form->get('state')->getErrors());
        self::assertStringContainsString(
            "doesn't belong to this country",
            (string) $form->get('state')->getErrors()[0]->getMessage(),
        );
    }

    public function testACountryRequiringAStateStillRejectsAnEmptySelection(): void
    {
        $required = $this->country(required: true);
        $this->state($required);

        $form = $this->submit($required, ['state' => '']);

        self::assertCount(1, $form->get('state')->getErrors());
    }

    public function testACountryRequiringAStateThatHoldsNoneDoesNotBlockTheCustomer(): void
    {
        $required = $this->country(required: true);

        $form = $this->submit($required, ['state' => '']);

        self::assertCount(0, $form->get('state')->getErrors(), 'an empty list leaves nothing to select');
    }

    public function testAnOptionalStateMayBeLeftEmpty(): void
    {
        $optional = $this->country(required: false);
        $this->state($optional);

        $form = $this->submit($optional, ['state' => '']);

        self::assertCount(0, $form->get('state')->getErrors());
    }

    /**
     * @param array<string, string> $overrides
     */
    private function submit(Country $builtWith, array $overrides): FormInterface
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(
                AddressCreateForm::class,
                FormType::class,
                ['country' => $builtWith->getId()],
                ['csrf_protection' => false],
            )
            ->getForm();

        $form->submit(array_merge([
            'label' => 'Home',
            'title' => (string) $this->title->getId(),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => '1 Main Street',
            'city' => 'Springfield',
            'zipcode' => '12345',
            'country' => (string) $builtWith->getId(),
            'state' => '',
        ], $overrides));

        return $form;
    }

    private function country(bool $required): Country
    {
        $suffix = ++$this->sequence;

        $country = $this->createFixtureFactory()->country([
            'isocode' => (string) (900 + $suffix),
            'isoalpha2' => 'Z'.$suffix,
            'isoalpha3' => 'ZZ'.$suffix,
            'shopCountry' => false,
        ]);
        $country->setHasStates($required ? 1 : 0)->save();

        return $country;
    }

    private function state(Country $country): State
    {
        $state = new State();
        $state->setCountry($country);
        $state->setIsocode('S'.$this->sequence);
        $state->setVisible(1);

        // The form reads the title in the locale of the session, whichever it is.
        foreach (LangQuery::create()->find() as $lang) {
            $state->setLocale($lang->getLocale())->setTitle('Province '.$this->sequence);
        }

        $state->save();

        return $state;
    }
}
