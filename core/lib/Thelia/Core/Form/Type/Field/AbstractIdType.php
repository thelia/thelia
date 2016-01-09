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

namespace Thelia\Core\Form\Type\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Class AbstractIdType
 * @package Thelia\Core\Form\Type\Field
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractIdType extends AbstractType
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "required" => true,
            "constraints" => array(
                new NotBlank(),
                new Callback([
                    "methods" => array(
                        [$this, "checkId"],
                    ),
                ]),
            ),
            "cascade_validation" => true,
        ]);
    }

    public function getParent()
    {
        return "integer";
    }

    public function checkId($value, ExecutionContextInterface $context)
    {
        if (null === $this->getQuery()->findPk($value)) {
            $context->addViolation(
                $this->translator->trans(
                    "The %obj_name id '%id' doesn't exist",
                    [
                        "%obj_name" => $this->getObjName(),
                        "%id" => $value,
                    ]
                )
            );
        }
    }

    protected function getObjName()
    {
        if (preg_match("#^(.+)_id$#i", $this->getName(), $match)) {
            return $match[1];
        }

        return $this->getName();
    }

    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     *
     * Get the model query to check
     */
    abstract protected function getQuery();
}
