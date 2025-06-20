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
namespace Thelia\Core\Form\Type\Field;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class AbstractIdType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class AbstractIdType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => true,
            'constraints' => [
                new NotBlank(),
                new Callback($this->checkId(...)),
            ],
            'cascade_validation' => true,
        ]);
    }

    public function getParent(): ?string
    {
        return IntegerType::class;
    }

    public function checkId($value, ExecutionContextInterface $context): void
    {
        if (null === $this->getQuery()->findPk($value)) {
            $context->addViolation(
                Translator::getInstance()->trans(
                    "The %obj_name id '%id' doesn't exist",
                    [
                        '%obj_name' => $this->getObjName(),
                        '%id' => $value,
                    ]
                )
            );
        }
    }

    protected function getObjName()
    {
        if (preg_match('#^(.+)_id$#i', (string) $this->getName(), $match)) {
            return $match[1];
        }

        return $this->getName();
    }

    abstract public function getName();

    /**
     * @return ModelCriteria
     *
     * Get the model query to check
     */
    abstract protected function getQuery();
}
