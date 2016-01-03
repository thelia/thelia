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


namespace Thelia\Form;

use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;

/**
 * Class AddressCountryValidationTrait
 * @package Thelia\Form
 * @author Julien Chanséaume <julien@thelia.net>
 */
trait AddressCountryValidationTrait
{

    public function verifyZipCode($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if (null !== $country = CountryQuery::create()->findPk($data['country'])) {
            if ($country->getNeedZipCode()) {
                $zipCodeRegExp = $country->getZipCodeRE();
                if (null !== $zipCodeRegExp) {
                    if (!preg_match($zipCodeRegExp, $data['zipcode'])) {
                        $context->addViolation(
                            Translator::getInstance()->trans(
                                "This zip code should respect the following format : %format.",
                                ['%format' => $country->getZipCodeFormat()]
                            )
                        );
                    }
                }
            }
        }
    }

    public function verifyState($value, ExecutionContextInterface $context)
    {
        $data = $context->getRoot()->getData();

        if (null !== $country = CountryQuery::create()->findPk($data['country'])) {
            if ($country->getHasStates()) {
                if (null !== $state = StateQuery::create()->findPk($data['state'])) {
                    if ($state->getCountryId() !== $country->getId()) {
                        $context->addViolation(
                            Translator::getInstance()->trans(
                                "This state doesn't belong to this country."
                            )
                        );
                    }
                } else {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            "You should select a state for this country."
                        )
                    );
                }
            }
        }
    }

}
