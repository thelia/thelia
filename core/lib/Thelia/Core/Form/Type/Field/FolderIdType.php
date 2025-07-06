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
use Thelia\Model\FolderQuery;

/**
 * Class FolderIdType.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class FolderIdType extends AbstractIdType
{
    /**
     * @return ModelCriteria
     *
     * Get the model query to check
     */
    protected function getQuery(): FolderQuery
    {
        return new FolderQuery();
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName(): string
    {
        return 'folder_id';
    }
}
