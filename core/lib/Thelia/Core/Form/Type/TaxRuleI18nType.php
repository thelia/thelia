<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Thelia\Form\StandardDescriptionFieldsTrait;

/**
 * Class TaxRuleI18nType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class TaxRuleI18nType extends AbstractTheliaType
{
    use StandardDescriptionFieldsTrait;

    /**
     * @var FormBuilderInterface
     *
     * This is used for compatibility with the StandardDescriptionFieldsTrait
     */
    protected $formBuilder;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'cascade_validation' => true,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->formBuilder = $builder;

        $this->addStandardDescFields(['chapo', 'postscriptum']);
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'tax_rule_i18n';
    }
}
