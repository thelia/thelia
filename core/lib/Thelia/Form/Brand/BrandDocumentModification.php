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

namespace Thelia\Form\Brand;

use Thelia\Form\Image\DocumentModification;

/**
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class BrandDocumentModification extends DocumentModification
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'thelia_brand_document_modification';
    }
}
