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
namespace Thelia\Form\Lang;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;
use Thelia\Model\LangQuery;

/**
 * Class LangUrlForm.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class LangUrlForm extends BaseForm
{
    public const LANG_PREFIX = 'url_';

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
        foreach (LangQuery::create()->find() as $lang) {
            $this->formBuilder->add(
                self::LANG_PREFIX.$lang->getId(),
                TextType::class,
                [
                    'constraints' => [
                        new NotBlank(),
                    ],
                    'attr' => [
                        'tag' => 'url',
                        'url_id' => $lang->getId(),
                        'url_title' => $lang->getTitle(),
                    ],
                ]
            );
        }
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName(): string
    {
        return 'thelia_language_url';
    }
}
