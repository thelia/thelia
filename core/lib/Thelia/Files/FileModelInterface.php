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
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
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
     * Get the form instance used to change this object information
     *
     * @param Request $request the current request
     *
     * @return BaseForm the form
     */
    public function getUpdateFormInstance(Request $request);
    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir();

    /**
     * @param int $objectId the object ID
     *
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl($objectId);

    /**
     * Get the Query instance for this object
     *
     * @return ModelCriteria
     */
    public function getQueryInstance();

    public function save();

    public function delete();

    public function getId();


    /**
     * Set the current title
     *
     * @param string $title the title in the current locale
     * @return FileModelInterface
     */
    public function setTitle($title);

    public function setChapo($chapo);
    public function setDescription($description);
    public function setPostscriptum($postscriptum);

    /**
     * Set the current locale
     *
     * @param string $locale the locale string
     * @return FileModelInterface
     */
    public function setLocale($locale);
}