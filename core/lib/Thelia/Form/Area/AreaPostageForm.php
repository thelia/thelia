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

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Class AreaPostageForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaPostageForm extends BaseForm
{
    /**
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :.
     *
     * $this->formBuilder->add("name", TextType::class)
     *   ->add("email", EmailType::class, array(
     *           "attr" => array(
     *               "class" => "field"
     *           ),
     *           "label" => "email",
     *           "constraints" => array(
     *               new \Symfony\Component\Validator\Constraints\NotBlank()
     *           )
     *       )
     *   )
     *   ->add('age', IntegerType::class);
     *
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add('area_id', IntegerType::class, [
                'constraints' => [
                    new GreaterThan(['value' => 0]),
                    new NotBlank(),
                ],
            ])
            ->add('postage', NumberType::class, [
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0]),
                    new NotBlank(),
                ],
                'label_attr' => ['for' => 'area_postage'],
                'label' => Translator::getInstance()->trans('Postage'),
            ])
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_area_postage';
    }
}
