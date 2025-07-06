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
namespace Thelia\Form;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;

/**
 * Class AddressCountryValidationTrait.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
trait AddressCountryValidationTrait
{
    public function verifyZipCode($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (null !== ($country = CountryQuery::create()->findPk($data['country'])) && $country->getNeedZipCode()) {
            $zipCodeRegExp = $country->getZipCodeRE();
            if (null !== $zipCodeRegExp && !preg_match($zipCodeRegExp, (string) $data['zipcode'])) {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        'This zip code should respect the following format : %format.',
                        ['%format' => $country->getZipCodeFormat()]
                    )
                );
            }
        }
    }

    public function verifyState($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        if (null !== ($country = CountryQuery::create()->findPk($data['country'])) && $country->getHasStates()) {
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
                        'You should select a state for this country.'
                    )
                );
            }
        }
    }

    public function verifyCity($value, ExecutionContextInterface $context): void
    {
        $data = $context->getRoot()->getData();

        $re = '/\D+/';

        if (!preg_match($re, (string) $data['city'], $matches, \PREG_OFFSET_CAPTURE, 0)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    'Your city can only contains letters.'
                )
            );
        }
    }
}
