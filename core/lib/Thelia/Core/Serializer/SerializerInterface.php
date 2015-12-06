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

namespace Thelia\Core\Serializer;

/**
 * Interface SerializerInterface
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 */
interface SerializerInterface
{
    /**
     * Get serializer identifier
     *
     * @return string The serializer identifier
     */
    public function getId();

    /**
     * Get serializer name
     *
     * @return string The serializer name
     */
    public function getName();

    /**
     * Get serializer extension
     *
     * @return string The serializer extension
     */
    public function getExtension();

    /**
     * Get serializer mime type
     *
     * @return string The serializer mime type
     */
    public function getMimeType();

    /**
     * Get start string that must wrap serialized data
     *
     * @return string Wrap opening string
     */
    public function wrapOpening();

    /**
     * Serialize data
     *
     * @param mixed $data Data to serialize
     *
     * @return string Serialized data
     */
    public function serialize($data);

    /**
     * Get string that separate serialized data
     *
     * @return string Wrap separator string
     */
    public function separator();

    /**
     * Get close string that must wrap serialized data
     *
     * @return string Wrap closing string
     */
    public function wrapClosing();

    public function unserialize();
}
