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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Clock\Clock;
use Thelia\Core\File\FileModelParentInterface;
use Thelia\Domain\CMS\Publication\Exception\EmptyContentPublicationException;
use Thelia\Domain\CMS\Publication\PublicationGuard;
use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\CMS\Publication\PublishableInterface;
use Thelia\Domain\CMS\Publication\VisibilityDatesAligner;
use Thelia\Model\Base\Content as BaseContent;
use Thelia\Model\Map\ContentTableMap;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;

class Content extends BaseContent implements FileModelParentInterface, PublishableInterface
{
    use PositionManagementTrait;
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName()
    {
        return 'content';
    }

    /**
     * Calculate next position relative to our parent.
     *
     * @deprecated since 2.3, and will be removed in 2.4
     */
    protected function addCriteriaToPositionQuery(ContentQuery $query): void
    {
        $contents = ContentFolderQuery::create()
            ->filterByFolderId($this->getDefaultFolderId())
            ->filterByDefaultFolder(true)
            ->select('content_id')
            ->find();

        // Filtrer la requete sur ces produits
        if (null !== $contents) {
            $query->filterById($contents, Criteria::IN);
        }
    }

    public function getDefaultFolderId(): int
    {
        // Find default folder
        $default_folder = ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByDefaultFolder(true)
            ->findOne();

        return null === $default_folder ? 0 : $default_folder->getFolderId();
    }

    /**
     * @return $this
     */
    public function setDefaultFolder(int $defaultFolderId)
    {
        // Allow uncategorized content (NULL instead of 0, to bypass delete cascade constraint)
        if ($defaultFolderId <= 0) {
            $defaultFolderId = null;
        }

        $contentFolder = ContentFolderQuery::create()
            ->filterByContentId($this->getId())
            ->filterByDefaultFolder(true)
            ->findOne();

        if (null !== $contentFolder && (int) $contentFolder->getFolderId() === (int) $defaultFolderId) {
            return $this;
        }

        if (null !== $contentFolder) {
            $contentFolder->delete();
        }

        // checks if the content is already associated with the folder and but not default
        if (null !== $contentFolder = ContentFolderQuery::create()->filterByContent($this)->filterByFolderId($defaultFolderId)->findOne()) {
            $contentFolder->setDefaultFolder(1)->save();
        } else {
            $position = (new ContentFolder())->setFolderId($defaultFolderId)->getNextPosition();

            (new ContentFolder())
                ->setContent($this)
                ->setFolderId($defaultFolderId)
                ->setDefaultFolder(1)
                ->setPosition($position)
                ->save();

            // For BC, will be removed in 2.4
            $this->setPosition($position);
        }

        return $this;
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use Content::setDefaultFolder
     *
     * @return $this
     */
    public function updateDefaultFolder(int $defaultFolderId)
    {
        return $this->setDefaultFolder($defaultFolderId);
    }

    /**
     * Create a new content.
     *
     * Here pre and post insert event are fired
     *
     * @return $this Return $this, allow chaining
     *
     * @throws \Exception
     */
    public function create($defaultFolderId)
    {
        $con = Propel::getWriteConnection(ContentTableMap::DATABASE_NAME);

        $con->beginTransaction();

        try {
            $this->save($con);

            $this->setDefaultFolder($defaultFolderId)->save($con);

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollback();

            throw $exception;
        }

        return $this;
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolder::setPosition
     */
    public function setPosition(?int $v = null): static
    {
        return parent::setPosition($v);
    }

    /**
     * @deprecated since 2.3, and will be removed in 2.4, please use ContentFolder::getPosition
     */
    public function getPosition(): ?int
    {
        return parent::getPosition();
    }

    /**
     * An existing content going from hidden to visible needs a body in the default
     * language, whichever path writes the flag (publication event, update or toggle
     * event, facade, admin API, scheduled command). A new content is not checked. The
     * dates are then kept in step with the flag, so the state read from the row is
     * always the one the flag says.
     *
     * @throws EmptyContentPublicationException
     */
    public function preSave(?ConnectionInterface $con = null): bool
    {
        $visibilityChanged = !$this->isNew() && $this->isColumnModified(ContentTableMap::COL_VISIBLE);

        if ($visibilityChanged && 1 === (int) $this->getVisible()) {
            $locale = PublicationGuard::defaultLocale();
            // The translation held in memory carries the body being saved along with the
            // flag; it is not in the database yet. Reading it through getTranslation() would
            // switch the locale of the row, so the array is read directly.
            $bodyInMemory = isset($this->currentTranslations[$locale]) ? $this->currentTranslations[$locale]->getDescription() : null;

            try {
                (new PublicationGuard())->assertHasBody($this, $bodyInMemory, $locale);
            } catch (EmptyContentPublicationException $exception) {
                // With the instance pool on, the row in memory is the one every later read of
                // this content gets: left as it is, it says visible, and the next save of the
                // same content would put it online after all.
                $this->reload(false, $con);

                throw $exception;
            }
        }

        if ($visibilityChanged) {
            VisibilityDatesAligner::alignOn($this, Clock::get()->now());
        }

        return parent::preSave($con);
    }

    /**
     * Written as it is compared: in the timezone of PHP, at the second (see
     * {@see PublicationMoment}), whichever path writes it: the service, the admin API,
     * a module.
     */
    public function setPublishAt(string|int|\DateTimeInterface|null $v = null): static
    {
        return parent::setPublishAt(PublicationMoment::atTheSecondOrNull($v));
    }

    public function setUnpublishAt(string|int|\DateTimeInterface|null $v = null): static
    {
        return parent::setUnpublishAt(PublicationMoment::atTheSecondOrNull($v));
    }

    public function postSave(?ConnectionInterface $con = null): void
    {
        // For BC, will be removed in 2.4
        if (!$this->isNew() && (isset($this->modifiedColumns[ContentTableMap::COL_POSITION]) && $this->modifiedColumns[ContentTableMap::COL_POSITION]) && null !== $productCategory = ContentFolderQuery::create()
            ->filterByContent($this)
            ->filterByDefaultFolder(true)
            ->findOne()) {
            $productCategory->changeAbsolutePosition($this->getPosition());
        }

        parent::postSave();
    }

    /**
     * Overload for the position management.
     */
    protected function doAddContentFolder(ContentFolder $contentFolder): void
    {
        parent::doAddContentFolder($contentFolder);

        // Propel associates a link every time it hydrates one, not only when a
        // link is being created: the join that reads a folder with its
        // contents lands here once per row. Numbering there cost a query per row
        // and replaced the position the row carries with a number nothing writes
        // back.
        if (!$contentFolder->isNew()) {
            return;
        }

        $contentFolderPosition = ContentFolderQuery::create()
            ->filterByFolderId($contentFolder->getFolderId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        $contentFolder->setPosition(null !== $contentFolderPosition ? $contentFolderPosition->getPosition() + 1 : 1);
    }
}
