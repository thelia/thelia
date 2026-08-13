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
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Form\AddressCreateForm;
use Thelia\Form\AddressUpdateForm;
use Thelia\Form\CustomerCreateForm;
use Thelia\Model\Country;
use Thelia\Model\LangQuery;
use Thelia\Model\State;
use Thelia\Test\IntegrationTestCase;

/**
 * Hiding a state in the back office must remove it from every address form.
 *
 * The three forms below share getStatesChoices(), so a province retired by the
 * shop was still offered at registration and on both address screens.
 */
final class AddressFormStateChoicesTest extends IntegrationTestCase
{
    private Country $country;
    private State $offered;
    private State $hidden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = $this->createFixtureFactory()->country([
            'isocode' => '901',
            'isoalpha2' => 'ZZ',
            'isoalpha3' => 'ZZZ',
        ]);

        $this->offered = $this->createState('ON', 'Offered province', 1);
        $this->hidden = $this->createState('OFF', 'Retired province', 0);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function addressFormProvider(): iterable
    {
        yield 'address creation' => [AddressCreateForm::class];
        yield 'address update' => [AddressUpdateForm::class];
        yield 'customer registration' => [CustomerCreateForm::class];
    }

    #[DataProvider('addressFormProvider')]
    public function testAStateHiddenInTheBackOfficeIsNotOffered(string $formClass): void
    {
        $choices = $this->stateChoicesOf($formClass);

        self::assertContains($this->offered->getId(), $choices, 'a visible state must stay selectable');
        self::assertNotContains($this->hidden->getId(), $choices, 'a hidden state must not be selectable');
    }

    /**
     * @return array<string, int>
     */
    private function stateChoicesOf(string $formClass): array
    {
        $form = $this->getService(TheliaFormFactory::class)
            ->createForm(
                $formClass,
                FormType::class,
                ['country' => $this->country->getId()],
                ['csrf_protection' => false],
            )
            ->getForm();

        return $form->get('state')->getConfig()->getOption('choices');
    }

    private function createState(string $isoCode, string $title, int $visible): State
    {
        $state = new State();
        $state->setCountry($this->country);
        $state->setIsocode($isoCode);
        $state->setVisible($visible);

        // The form reads the title in the locale of the session, whichever it is.
        foreach (LangQuery::create()->find() as $lang) {
            $state->setLocale($lang->getLocale())->setTitle($title);
        }

        $state->save();

        return $state;
    }
}
