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


namespace TheliaMigrateCountry\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Form\Type\AbstractTheliaType;
use Thelia\Core\Translation\Translator;
use Thelia\Model\StateQuery;

/**
 * Class CountryStateMigrationType
 * @package TheliaMigrateCountry\Form\Type
 * @author Julien Chanséaume <julien@thelia.net>
 */
class CountryStateMigrationType extends AbstractTheliaType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                "cascade_validation" => true,
                "constraints" => array(
                    new Callback([
                        "methods" => array(
                            [$this, "checkStateId"],
                        ),
                    ]),
                ),
            ]
        );
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("migrate", "checkbox")
            ->add(
                "country",
                "country_id"
            )
            ->add(
                "new_country",
                "country_id"
            )
            ->add(
                "new_state",
                "state_id",
                [
                    "constraints" => [],
                ]
            )
        ;
    }

    public function checkStateId($value, ExecutionContextInterface $context)
    {

        if ($value['migrate']) {
            if (null !== $state = StateQuery::create()->findPk($value['new_state'])) {
                if ($state->getCountryId() !== $value['new_country']) {
                    $context->addViolation(
                        Translator::getInstance()->trans(
                            "The state id '%id' does not belong to country id '%id_country'",
                            [
                                '%id' => $value['new_state'],
                                '%id_country' => $value['new_country']
                            ]
                        )
                    );
                }
            } else {
                $context->addViolation(
                    Translator::getInstance()->trans(
                        "The state id '%id' doesn't exist",
                        ['%id' => $value['new_state']]
                    )
                );
            }
        }

    }

    private function getRowData(ExecutionContextInterface $context)
    {
        $propertyPath = $context->getPropertyPath();
        $data = $this->getRowData($context);


    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'country_state_migration';
    }
}
