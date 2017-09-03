<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Base\FolderQuery as BaseFolderQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'folder' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class FolderQuery extends BaseFolderQuery
{
    /**
     *
     * count how many direct contents a folder has
     *
     * @param  int $parent folder id
     * @return int
     */
    public static function countChild($parent)
    {
        return self::create()->filterByParent($parent)->count();
    }

    /**
     * find all contents for a given folder.
     *
     * @param  int $folderId the folder id or an array of id
     * @param  int                    $depth           max depth you want to search
     * @param  int                    $currentPosition don't change this param, it is used for recursion
     * @return \Thelia\Model\Folder[]
     */
    public static function findAllChild($folderId, $depth = 0, $currentPosition = 0)
    {
        $result = array();

        if (is_array($folderId)) {
            foreach ($folderId as $folderSingleId) {
                $result = array_merge($result, (array) self::findAllChild($folderSingleId, $depth, $currentPosition));
            }
        } else {
            $currentPosition++;

            if ($depth == $currentPosition && $depth != 0) {
                return[];
            }

            $categories = self::create()
                ->filterByParent($folderId)
                ->find();

            foreach ($categories as $folder) {
                array_push($result, $folder);
                $result = array_merge($result, (array) self::findAllChild($folder->getId(), $depth, $currentPosition));
            }
        }

        return $result;
    }

    /**
     * Return all folder IDs of a folder tree, starting at $folderId, up to a depth of $depth
     *
     * @param  int|int[] $folderId the folder id or an array of folder ids
     * @param  int $depth max tree traversal depth
     * @return int[]
     * @since 2.3
     */
    public static function getFolderTreeIds($folderId, $depth = 1)
    {
        $result = is_array($folderId) ? $folderId : [ $folderId ];

        if ($depth > 1) {
            $folders = self::create()
                ->filterByParent($folderId, Criteria::IN)
                ->withColumn('id')
                ->find();

            foreach ($folders as $folder) {
                $result = array_merge(
                    $result,
                    self::getFolderTreeIds($folder->getId(), $depth - 1)
                );
            }
        }

        return $result;
    }
}
// FolderQuery
