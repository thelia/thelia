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
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\ImportExport\Import\AbstractImport;

/**
 * Class ImportEvent.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportEvent extends Event
{
    /**
     * @var array Errors list
     */
    protected $errors = [];

    /**
     * Event constructor.
     *
     * @param AbstractImport $import An import
     * @param SerializerInterface $serializer A serializer interface
     */
    public function __construct(protected AbstractImport $import, protected SerializerInterface $serializer)
    {
    }

    /**
     * Get import.
     *
     * @return AbstractImport An import
     */
    public function getImport(): AbstractImport
    {
        return $this->import;
    }

    /**
     * Set import.
     *
     * @param AbstractImport $import An import
     *
     * @return $this Return $this, allow chaining
     */
    public function setImport(AbstractImport $import): static
    {
        $this->import = $import;

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
     * Get errors.
     *
     * @return array Errors list
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set errors.
     *
     * @param array $errors Errors list
     *
     * @return $this Return $this, allow chaining
     */
    public function setErrors(array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }
}
