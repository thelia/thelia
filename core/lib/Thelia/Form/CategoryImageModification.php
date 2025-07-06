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

namespace Thelia\Form;

use Thelia\Form\Image\ImageModification;

/**
 * Created by JetBrains PhpStorm.
 * Date: 9/18/13
 * Time: 3:56 PM.
 *
 * Form allowing to process an image collection
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class CategoryImageModification extends ImageModification
{
    /**
     * Get form name
     * This name must be unique.
     */
    public static function getName(): string
    {
        return 'thelia_category_image_modification';
    }
}
