<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace TheliaMigrateCountry\Controller;

use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Map\AddressTableMap;
use Thelia\Model\Map\CountryAreaTableMap;
use Thelia\Model\Map\TaxRuleCountryTableMap;
use TheliaMigrateCountry\Events\MigrateCountryEvent;
use TheliaMigrateCountry\Events\MigrateCountryEvents;


/**
 * Class MigrateController
 * @package TheliaMigrateCountry\Controller
 * @author Julien Chanséaume <julien@thelia.net>
 */
class MigrateController extends BaseAdminController
{
    protected $useFallbackTemplate = true;

    public function migrateSystemAction()
    {
        $response = $this->checkAuth(AdminResources::COUNTRY, array(), AccessManager::UPDATE);
        if (null !== $response) {
            return $response;
        }

        // load country not migrated
        $dataForm = [];

        $migratedCountries = json_decode(ConfigQuery::read('thelia_country_state_migration', '[]'), true);

        $countries = CountryQuery::create()
            ->filterByHasStates(1)
        ;

        /** @var Country $country */
        foreach ($countries as $country) {
            $oldCountries = CountryQuery::create()
                ->filterByHasStates(0)
                ->filterByIsocode($country->getIsoCode())
                ->find()
            ;
            /** @var Country $oldCountry */
            foreach ($oldCountries as $oldCountry) {
                if (!isset($migratedCountries[$oldCountry->getId()])) {
                    $dataForm[] = [
                        'migrate' => false,
                        'country' => $oldCountry->getId(),
                        'new_country' => $country->getId(),
                        'new_state' => null
                    ];
                }
            }
        }

        // prepare form
        $form = $this->createForm('thelia.admin.country.state.migration', 'form', ['migrations' => $dataForm]);
        $this->getParserContext()->addForm($form);

        return $this->render(
            'countries-migrate',
            [
                'countriesMigrated' => $migratedCountries,
                'showForm' => count($dataForm) != 0
            ]
        );
    }

    public function doMigrateSystemAction()
    {
        $response = $this->checkAuth(AdminResources::COUNTRY, array(), AccessManager::UPDATE);
        if (null !== $response) {
            return $response;
        }

        $changeForm = $this->createForm('thelia.admin.country.state.migration', 'form');

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($changeForm, "POST");

            // Get the form field values
            $data = $form->getData();

            foreach ($data['migrations'] as $migration) {

                if (!$migration['migrate']) {
                    continue;
                }

                $changeEvent = new MigrateCountryEvent(
                    $migration['country'],
                    $migration['new_country'],
                    $migration['new_state']
                );

                $this->dispatch(MigrateCountryEvents::MIGRATE_COUNTRY, $changeEvent);

                // memorize the migration
                $migratedCountries = json_decode(ConfigQuery::read('thelia_country_state_migration', '[]'), true);
                $migratedCountries[$changeEvent->getCountry()] = [
                    'country' => $changeEvent->getNewCountry(),
                    'state' => $changeEvent->getNewState(),
                    'counter' => $changeEvent->getCounter()
                ];
                ConfigQuery::write('thelia_country_state_migration', json_encode($migratedCountries));

                // message
                $message = $this->getTranslator()->trans(
                    'Country %id migrated to country (ID %country) and state (ID %state) (address: %address, tax rules: %tax, shipping zones: %zone)',
                    [
                        '%id' => $changeEvent->getCountry(),
                        '%country' => $changeEvent->getNewCountry(),
                        '%state' => $changeEvent->getNewState(),
                        '%address' => $changeEvent->getCounter()[AddressTableMap::TABLE_NAME],
                        '%tax' => $changeEvent->getCounter()[TaxRuleCountryTableMap::TABLE_NAME],
                        '%zone' => $changeEvent->getCounter()[CountryAreaTableMap::TABLE_NAME]
                    ]
                );

                // add flash message
                $this->getSession()->getFlashBag()->add('migrate', $message);

                // Log migration
                $this->adminLogAppend(
                    AdminResources::COUNTRY,
                    AccessManager::UPDATE,
                    $message,
                    $changeEvent->getCountry()
                );

            }

            return $this->generateSuccessRedirect($changeForm);
        } catch (FormValidationException $ex) {
            // Form cannot be validated
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        }

        if (false !== $error_msg) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("Country migration"),
                $error_msg,
                $changeForm,
                $ex
            );

            return $this->render(
                'countries-migrate',
                [
                    'countriesMigrated' => $migratedCountries,
                    'showForm' => true
                ]
            );
        }
    }
}
