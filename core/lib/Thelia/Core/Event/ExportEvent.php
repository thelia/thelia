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
namespace Thelia\Core\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Thelia\Core\Archiver\ArchiverInterface;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\ImportExport\Export\AbstractExport;

/**
 * Class ExportEvent.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ExportEvent extends Event
{
    /**
     * @var string Path to generated export
     */
    protected $filePath;

    /**
     * Event constructor.
     *
     * @param AbstractExport $export An export
     * @param SerializerInterface $serializer A serializer interface
     * @param ArchiverInterface $archiver An archiver interface
     */
    public function __construct(protected AbstractExport $export, protected SerializerInterface $serializer, protected ?ArchiverInterface $archiver = null)
    {
    }

    /**
     * Get export.
     *
     * @return AbstractExport An export
     */
    public function getExport(): AbstractExport
    {
        return $this->export;
    }

    /**
     * Set export.
     *
     * @param AbstractExport $export An export
     *
     * @return $this Return $this, allow chaining
     */
    public function setExport(AbstractExport $export): self
    {
        $this->export = $export;

        return $this;
    }

    /**
     * Get serializer.
     *
     * @return SerializerInterface A serializer interface
     */
    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * Set serializer.
     *
     * @param SerializerInterface $serializer A serializer interface
     *
     * @return $this Return $this, allow chaining
     */
    public function setSerializer(SerializerInterface $serializer): static
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Get archiver.
     *
     * @return mixed|ArchiverInterface An archiver interface
     */
    public function getArchiver(): ?ArchiverInterface
    {
        return $this->archiver;
    }

    /**
     * Set archiver.
     *
     * @param mixed|ArchiverInterface $archiver An archiver interface
     *
     * @return $this Return $this, allow chaining
     */
    public function setArchiver(ArchiverInterface $archiver = null): static
    {
        $this->archiver = $archiver;

        return $this;
    }

    /**
     * Get export file path.
     *
     * @return string Export file path
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set export file path.
     *
     * @param string $filePath Export file path
     *
     * @return $this Return $this, allow chaining
     */
    public function setFilePath($filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }
}
