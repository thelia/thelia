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

namespace Thelia\Tests\Core\Template\Loop;

use Thelia\Core\Template\Loop\Document;
use Thelia\Model\CategoryDocumentQuery;
use Thelia\Model\ContentDocumentQuery;
use Thelia\Model\FolderDocumentQuery;
use Thelia\Model\ProductDocumentQuery;
use Thelia\Tests\Core\Template\Element\BaseLoopTestor;

/**
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 */
class DocumentTest extends BaseLoopTestor
{
    public function getTestedClassName()
    {
        return 'Thelia\Core\Template\Loop\Document';
    }

    public function getMandatoryArguments()
    {
        return ['source' => 'product', 'id' => 1];
    }

    public function testSearchByProductId()
    {
        $document = ProductDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), ['source' => 'product']);
    }

    public function testSearchByFolderId()
    {
        $document = FolderDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), ['source' => 'folder']);
    }

    public function testSearchByContentId()
    {
        $document = ContentDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), ['source' => 'content']);
    }

    public function testSearchByCategoryId()
    {
        $document = CategoryDocumentQuery::create()->findOne();

        $this->baseTestSearchById($document->getId(), ['source' => 'category']);
    }
}
