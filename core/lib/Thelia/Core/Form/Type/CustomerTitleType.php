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
namespace Thelia\Core\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Thelia\Core\Form\Type\Field\CustomerTitleIdType;

/**
 * Class CustomerTitleType.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class CustomerTitleType extends AbstractTheliaType
{
    public function __construct(protected CustomerTitleIdType $customerTitleIdType)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('i18n', 'collection', [
                'type' => 'customer_title_i18n',
                'allow_add' => true,
                'required' => true,
                'cascade_validation' => true,
                'constraints' => [
                    new Count(['min' => 1]),
                ],
            ])
            ->add('default', 'checkbox')
            ->add('title_id', 'customer_title_id', [
                'constraints' => $this->getConstraints($this->customerTitleIdType, 'update'),
            ])
        ;
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName(): string
    {
        return 'customer_title';
    }
}
