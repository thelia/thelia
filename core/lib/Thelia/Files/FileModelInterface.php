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

namespace Thelia\Files;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Form\BaseForm;

interface FileModelInterface
{
    /**
     * Set file parent id
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId);

    /**
     * Get file parent id
     *
     * @return int parent id
     */
    public function getParentId();

    /**
     * @return string the file name
     */
    public function getFile();

    /**
     * @param string $file the file name
     */
    public function setFile($file);

    /**
     * @return FileModelParentInterface the parent file model
     */
    public function getParentFileModel();

    /**
     * Get the ID of the form used to change this object information
     *
     * @return BaseForm the form
     */
    public function getUpdateFormId();

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir();

    /**
     * @param int $objectId the object ID
     *
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl();

    /**
     * Get the Query instance for this object
     *
     * @return ModelCriteria
     */
    public function getQueryInstance();

    /**
     * Save the model object.
     *
     * @return mixed
     */
    public function save();

    /**
     * Delete the model object.
     *
     * @return mixed
     */
    public function delete();

    /**
     * Get the model object ID
     *
     * @return int
     */
    public function getId();

    /**
     * Set the current title
     *
     * @param string $title the title in the current locale
     */
    public function setTitle($title);

    /**
     * Get the current title
     *
     * @param  string             $title the title in the current locale
     * @return FileModelInterface
     */
    public function getTitle();

    /**
     * Set the chapo
     *
     * @param  string             $chapo the chapo in the current locale
     * @return FileModelInterface
     */
    public function setChapo($chapo);

    /**
     * Set the description
     *
     * @param  string             $description the description in the current locale
     * @return FileModelInterface
     */
    public function setDescription($description);

    /**
     * Set the postscriptum
     *
     * @param  string             $postscriptum the postscriptum in the current locale
     * @return FileModelInterface
     */
    public function setPostscriptum($postscriptum);

    /**
     * Set the current locale
     *
     * @param  string             $locale the locale string
     * @return FileModelInterface
     */
    public function setLocale($locale);

    /**
     * Set the current locale
     *
     * @param  bool            $visible true if the file is visible, false otherwise
     * @return FileModelInterface
     */
    public function setVisible($visible);
}
