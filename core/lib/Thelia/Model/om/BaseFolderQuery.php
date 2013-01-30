<?php

namespace Thelia\Model\om;

use \Criteria;
use \Exception;
use \ModelCriteria;
use \ModelJoin;
use \PDO;
use \Propel;
use \PropelCollection;
use \PropelException;
use \PropelObjectCollection;
use \PropelPDO;
use Thelia\Model\ContentFolder;
use Thelia\Model\Document;
use Thelia\Model\Folder;
use Thelia\Model\FolderI18n;
use Thelia\Model\FolderPeer;
use Thelia\Model\FolderQuery;
use Thelia\Model\Image;
use Thelia\Model\Rewriting;

/**
 * Base class that represents a query for the 'folder' table.
 *
 *
 *
 * @method FolderQuery orderById($order = Criteria::ASC) Order by the id column
 * @method FolderQuery orderByParent($order = Criteria::ASC) Order by the parent column
 * @method FolderQuery orderByLink($order = Criteria::ASC) Order by the link column
 * @method FolderQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method FolderQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method FolderQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method FolderQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method FolderQuery groupById() Group by the id column
 * @method FolderQuery groupByParent() Group by the parent column
 * @method FolderQuery groupByLink() Group by the link column
 * @method FolderQuery groupByVisible() Group by the visible column
 * @method FolderQuery groupByPosition() Group by the position column
 * @method FolderQuery groupByCreatedAt() Group by the created_at column
 * @method FolderQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method FolderQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method FolderQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method FolderQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method FolderQuery leftJoinImage($relationAlias = null) Adds a LEFT JOIN clause to the query using the Image relation
 * @method FolderQuery rightJoinImage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Image relation
 * @method FolderQuery innerJoinImage($relationAlias = null) Adds a INNER JOIN clause to the query using the Image relation
 *
 * @method FolderQuery leftJoinDocument($relationAlias = null) Adds a LEFT JOIN clause to the query using the Document relation
 * @method FolderQuery rightJoinDocument($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Document relation
 * @method FolderQuery innerJoinDocument($relationAlias = null) Adds a INNER JOIN clause to the query using the Document relation
 *
 * @method FolderQuery leftJoinRewriting($relationAlias = null) Adds a LEFT JOIN clause to the query using the Rewriting relation
 * @method FolderQuery rightJoinRewriting($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Rewriting relation
 * @method FolderQuery innerJoinRewriting($relationAlias = null) Adds a INNER JOIN clause to the query using the Rewriting relation
 *
 * @method FolderQuery leftJoinContentFolder($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentFolder relation
 * @method FolderQuery rightJoinContentFolder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentFolder relation
 * @method FolderQuery innerJoinContentFolder($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentFolder relation
 *
 * @method FolderQuery leftJoinFolderI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the FolderI18n relation
 * @method FolderQuery rightJoinFolderI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the FolderI18n relation
 * @method FolderQuery innerJoinFolderI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the FolderI18n relation
 *
 * @method Folder findOne(PropelPDO $con = null) Return the first Folder matching the query
 * @method Folder findOneOrCreate(PropelPDO $con = null) Return the first Folder matching the query, or a new Folder object populated from the query conditions when no match is found
 *
 * @method Folder findOneById(int $id) Return the first Folder filtered by the id column
 * @method Folder findOneByParent(int $parent) Return the first Folder filtered by the parent column
 * @method Folder findOneByLink(string $link) Return the first Folder filtered by the link column
 * @method Folder findOneByVisible(int $visible) Return the first Folder filtered by the visible column
 * @method Folder findOneByPosition(int $position) Return the first Folder filtered by the position column
 * @method Folder findOneByCreatedAt(string $created_at) Return the first Folder filtered by the created_at column
 * @method Folder findOneByUpdatedAt(string $updated_at) Return the first Folder filtered by the updated_at column
 *
 * @method array findById(int $id) Return Folder objects filtered by the id column
 * @method array findByParent(int $parent) Return Folder objects filtered by the parent column
 * @method array findByLink(string $link) Return Folder objects filtered by the link column
 * @method array findByVisible(int $visible) Return Folder objects filtered by the visible column
 * @method array findByPosition(int $position) Return Folder objects filtered by the position column
 * @method array findByCreatedAt(string $created_at) Return Folder objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Folder objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseFolderQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseFolderQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Folder', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new FolderQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     FolderQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return FolderQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof FolderQuery) {
            return $criteria;
        }
        $query = new FolderQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return   Folder|Folder[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = FolderPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(FolderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return   Folder A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `PARENT`, `LINK`, `VISIBLE`, `POSITION`, `CREATED_AT`, `UPDATED_AT` FROM `folder` WHERE `ID` = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new Folder();
            $obj->hydrate($row);
            FolderPeer::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return Folder|Folder[]|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($stmt);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return PropelObjectCollection|Folder[]|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection($this->getDbName(), Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($stmt);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FolderPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FolderPeer::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(FolderPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the parent column
     *
     * Example usage:
     * <code>
     * $query->filterByParent(1234); // WHERE parent = 1234
     * $query->filterByParent(array(12, 34)); // WHERE parent IN (12, 34)
     * $query->filterByParent(array('min' => 12)); // WHERE parent > 12
     * </code>
     *
     * @param     mixed $parent The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByParent($parent = null, $comparison = null)
    {
        if (is_array($parent)) {
            $useMinMax = false;
            if (isset($parent['min'])) {
                $this->addUsingAlias(FolderPeer::PARENT, $parent['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($parent['max'])) {
                $this->addUsingAlias(FolderPeer::PARENT, $parent['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FolderPeer::PARENT, $parent, $comparison);
    }

    /**
     * Filter the query on the link column
     *
     * Example usage:
     * <code>
     * $query->filterByLink('fooValue');   // WHERE link = 'fooValue'
     * $query->filterByLink('%fooValue%'); // WHERE link LIKE '%fooValue%'
     * </code>
     *
     * @param     string $link The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByLink($link = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($link)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $link)) {
                $link = str_replace('*', '%', $link);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FolderPeer::LINK, $link, $comparison);
    }

    /**
     * Filter the query on the visible column
     *
     * Example usage:
     * <code>
     * $query->filterByVisible(1234); // WHERE visible = 1234
     * $query->filterByVisible(array(12, 34)); // WHERE visible IN (12, 34)
     * $query->filterByVisible(array('min' => 12)); // WHERE visible > 12
     * </code>
     *
     * @param     mixed $visible The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(FolderPeer::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(FolderPeer::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FolderPeer::VISIBLE, $visible, $comparison);
    }

    /**
     * Filter the query on the position column
     *
     * Example usage:
     * <code>
     * $query->filterByPosition(1234); // WHERE position = 1234
     * $query->filterByPosition(array(12, 34)); // WHERE position IN (12, 34)
     * $query->filterByPosition(array('min' => 12)); // WHERE position > 12
     * </code>
     *
     * @param     mixed $position The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(FolderPeer::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(FolderPeer::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FolderPeer::POSITION, $position, $comparison);
    }

    /**
     * Filter the query on the created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByCreatedAt('2011-03-14'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt('now'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt(array('max' => 'yesterday')); // WHERE created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $createdAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(FolderPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(FolderPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FolderPeer::CREATED_AT, $createdAt, $comparison);
    }

    /**
     * Filter the query on the updated_at column
     *
     * Example usage:
     * <code>
     * $query->filterByUpdatedAt('2011-03-14'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt('now'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt(array('max' => 'yesterday')); // WHERE updated_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $updatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(FolderPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(FolderPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FolderPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Image object
     *
     * @param   Image|PropelObjectCollection $image  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   FolderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByImage($image, $comparison = null)
    {
        if ($image instanceof Image) {
            return $this
                ->addUsingAlias(FolderPeer::ID, $image->getFolderId(), $comparison);
        } elseif ($image instanceof PropelObjectCollection) {
            return $this
                ->useImageQuery()
                ->filterByPrimaryKeys($image->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByImage() only accepts arguments of type Image or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Image relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function joinImage($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Image');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Image');
        }

        return $this;
    }

    /**
     * Use the Image relation Image object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ImageQuery A secondary query class using the current class as primary query
     */
    public function useImageQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinImage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Image', '\Thelia\Model\ImageQuery');
    }

    /**
     * Filter the query by a related Document object
     *
     * @param   Document|PropelObjectCollection $document  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   FolderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByDocument($document, $comparison = null)
    {
        if ($document instanceof Document) {
            return $this
                ->addUsingAlias(FolderPeer::ID, $document->getFolderId(), $comparison);
        } elseif ($document instanceof PropelObjectCollection) {
            return $this
                ->useDocumentQuery()
                ->filterByPrimaryKeys($document->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByDocument() only accepts arguments of type Document or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Document relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function joinDocument($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Document');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Document');
        }

        return $this;
    }

    /**
     * Use the Document relation Document object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\DocumentQuery A secondary query class using the current class as primary query
     */
    public function useDocumentQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinDocument($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Document', '\Thelia\Model\DocumentQuery');
    }

    /**
     * Filter the query by a related Rewriting object
     *
     * @param   Rewriting|PropelObjectCollection $rewriting  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   FolderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByRewriting($rewriting, $comparison = null)
    {
        if ($rewriting instanceof Rewriting) {
            return $this
                ->addUsingAlias(FolderPeer::ID, $rewriting->getFolderId(), $comparison);
        } elseif ($rewriting instanceof PropelObjectCollection) {
            return $this
                ->useRewritingQuery()
                ->filterByPrimaryKeys($rewriting->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByRewriting() only accepts arguments of type Rewriting or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Rewriting relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function joinRewriting($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Rewriting');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Rewriting');
        }

        return $this;
    }

    /**
     * Use the Rewriting relation Rewriting object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\RewritingQuery A secondary query class using the current class as primary query
     */
    public function useRewritingQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinRewriting($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Rewriting', '\Thelia\Model\RewritingQuery');
    }

    /**
     * Filter the query by a related ContentFolder object
     *
     * @param   ContentFolder|PropelObjectCollection $contentFolder  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   FolderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByContentFolder($contentFolder, $comparison = null)
    {
        if ($contentFolder instanceof ContentFolder) {
            return $this
                ->addUsingAlias(FolderPeer::ID, $contentFolder->getFolderId(), $comparison);
        } elseif ($contentFolder instanceof PropelObjectCollection) {
            return $this
                ->useContentFolderQuery()
                ->filterByPrimaryKeys($contentFolder->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentFolder() only accepts arguments of type ContentFolder or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentFolder relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function joinContentFolder($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentFolder');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'ContentFolder');
        }

        return $this;
    }

    /**
     * Use the ContentFolder relation ContentFolder object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentFolderQuery A secondary query class using the current class as primary query
     */
    public function useContentFolderQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinContentFolder($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentFolder', '\Thelia\Model\ContentFolderQuery');
    }

    /**
     * Filter the query by a related FolderI18n object
     *
     * @param   FolderI18n|PropelObjectCollection $folderI18n  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   FolderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByFolderI18n($folderI18n, $comparison = null)
    {
        if ($folderI18n instanceof FolderI18n) {
            return $this
                ->addUsingAlias(FolderPeer::ID, $folderI18n->getId(), $comparison);
        } elseif ($folderI18n instanceof PropelObjectCollection) {
            return $this
                ->useFolderI18nQuery()
                ->filterByPrimaryKeys($folderI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByFolderI18n() only accepts arguments of type FolderI18n or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the FolderI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function joinFolderI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('FolderI18n');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'FolderI18n');
        }

        return $this;
    }

    /**
     * Use the FolderI18n relation FolderI18n object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\FolderI18nQuery A secondary query class using the current class as primary query
     */
    public function useFolderI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinFolderI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'FolderI18n', '\Thelia\Model\FolderI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Folder $folder Object to remove from the list of results
     *
     * @return FolderQuery The current query, for fluid interface
     */
    public function prune($folder = null)
    {
        if ($folder) {
            $this->addUsingAlias(FolderPeer::ID, $folder->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     FolderQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(FolderPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     FolderQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(FolderPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     FolderQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(FolderPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     FolderQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(FolderPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     FolderQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(FolderPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     FolderQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(FolderPeer::CREATED_AT);
    }
    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    FolderQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'FolderI18n';

        return $this
            ->joinFolderI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    FolderQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_EN', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('FolderI18n');
        $this->with['FolderI18n']->setIsWithOneToMany(false);

        return $this;
    }

    /**
     * Use the I18n relation query object
     *
     * @see       useQuery()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    FolderI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'FolderI18n', 'Thelia\Model\FolderI18nQuery');
    }

}
