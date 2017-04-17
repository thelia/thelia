<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event;

use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Serializer\SerializerInterface;
use Thelia\ImportExport\Import\AbstractImport;

/**
 * Class ImportEvent
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
class ImportEvent extends Event
{
    /**
     * @var \Thelia\ImportExport\Import\AbstractImport An import
     */
    protected $import;

    /**
     * @var \Thelia\Core\Serializer\SerializerInterface A serializer interface
     */
    protected $serializer;

    /**
     * @var array Errors list
     */
    protected $errors = [];

    /**
     * Event constructor
     *
     * @param \Thelia\ImportExport\Import\AbstractImport  $import     An import
     * @param \Thelia\Core\Serializer\SerializerInterface $serializer A serializer interface
     */
    public function __construct(AbstractImport $import, SerializerInterface $serializer)
    {
        $this->import = $import;
        $this->serializer = $serializer;
    }

    /**
     * Get import
     *
     * @return \Thelia\ImportExport\Import\AbstractImport An import
     */
    public function getImport()
    {
        return $this->import;
    }

    /**
     * Set import
     *
     * @param \Thelia\ImportExport\Import\AbstractImport $import An import
     *
     * @return $this Return $this, allow chaining
     */
    public function setImport(AbstractImport $import)
    {
        $this->import = $import;

        return $this;
    }

    /**
     * Get serializer
     *
     * @return \Thelia\Core\Serializer\SerializerInterface A serializer interface
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

    /**
     * Set serializer
     *
     * @param \Thelia\Core\Serializer\SerializerInterface $serializer A serializer interface
     *
     * @return $this Return $this, allow chaining
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Get errors
     *
     * @return array Errors list
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set errors
     *
     * @param array $errors Errors list
     *
     * @return $this Return $this, allow chaining
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;

        return $this;
    }
}
