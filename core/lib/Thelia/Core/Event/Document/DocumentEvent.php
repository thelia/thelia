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
namespace Thelia\Core\Event\Document;

use Thelia\Core\Event\CachedFileEvent;

/**
 * Class DocumentEvent.
 */
class DocumentEvent extends CachedFileEvent
{
    protected $documentPath;

    protected $documentUrl;

    /**
     * Get Document path.
     *
     * @return string The document file path
     */
    public function getDocumentPath()
    {
        return $this->documentPath;
    }

    /**
     * Set Document path.
     *
     * @param string $documentPath the document file path
     *
     * @return $this
     */
    public function setDocumentPath($documentPath): static
    {
        $this->documentPath = $documentPath;

        return $this;
    }

    /**
     * Get Document URL.
     *
     * @return string The document URL
     */
    public function getDocumentUrl()
    {
        return $this->documentUrl;
    }

    /**
     * Set Document URL.
     *
     * @param string $documentUrl the document URL
     *
     * @return $this
     */
    public function setDocumentUrl($documentUrl): static
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }
}
