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

namespace Thelia\Core\Form\Type\Field;

use Thelia\Model\CategoryAssociatedContentQuery;

/**
 * Class CategoryAssociatedContentIdType.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class CategoryAssociatedContentIdType extends AbstractIdType
{
    /**
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     *
     * Get the model query to check
     */
    protected function getQuery()
    {
        return new CategoryAssociatedContentQuery();
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'category_associated_content_id';
    }
}
