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

namespace Thelia\Core\Serializer;

/**
 * Interface SerializerInterface.
 *
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
interface SerializerInterface
{
    /**
     * Get serializer identifier.
     *
     * @return string The serializer identifier
     */
    public function getId(): string;

    /**
     * Get serializer name.
     *
     * @return string The serializer name
     */
    public function getName(): string;

    /**
     * Get serializer extension.
     *
     * @return string The serializer extension
     */
    public function getExtension(): string;

    /**
     * Get serializer mime type.
     *
     * @return string The serializer mime type
     */
    public function getMimeType(): string;

    /**
     * Prepare file to receive serialized data.
     *
     * @param \SplFileObject $fileObject A file object
     */
    public function prepareFile(\SplFileObject $fileObject);

    /**
     * Serialize data.
     *
     * @param mixed $data Data to serialize
     *
     * @return string Serialized data
     */
    public function serialize(mixed $data): string;

    /**
     * Get string that separate serialized data.
     *
     * @return string|null Wrap separator string
     */
    public function separator(): ?string;

    /**
     * Finalize file with serialized data.
     *
     * @param \SplFileObject $fileObject A file object
     */
    public function finalizeFile(\SplFileObject $fileObject);

    /**
     * Unserialize data.
     *
     * @param \SplFileObject $fileObject A file object
     *
     * @return array Unserialized data
     */
    public function unserialize(\SplFileObject $fileObject): array;
}
