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

namespace Thelia\Form\Area;

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CountryQuery;
use Thelia\Model\StateQuery;

/**
 * Class CountryListValidationTrait.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
trait CountryListValidationTrait
{
    public function verifyCountryList($value, ExecutionContextInterface $context): void
    {
        $countryList = \is_array($value) ? $value : [$value];

        foreach ($countryList as $countryItem) {
            $item = explode('-', (string) $countryItem);

            if (\count($item) == 2) {
                $country = CountryQuery::create()->findPk($item[0]);
                if (null === $country) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            'Country ID %id not found',
                            ['%id' => $item[0]]
                        )
                    );
                }

                if ($item[1] == '0') {
                    continue;
                }

                $state = StateQuery::create()->findPk($item[1]);
                if (null === $state) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            'State ID %id not found',
                            ['%id' => $item[1]]
                        )
                    );
                }
            } else {
                $context->addViolation(Translator::getInstance()->trans('Wrong country definition'));
            }
        }
    }
}
