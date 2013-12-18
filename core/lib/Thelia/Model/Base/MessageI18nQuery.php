<?php

namespace Thelia\Model\Base;

use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\MessageI18n as ChildMessageI18n;
use Thelia\Model\MessageI18nQuery as ChildMessageI18nQuery;
use Thelia\Model\Map\MessageI18nTableMap;

/**
 * Base class that represents a query for the 'message_i18n' table.
 *
 *
 *
 * @method     ChildMessageI18nQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildMessageI18nQuery orderByLocale($order = Criteria::ASC) Order by the locale column
 * @method     ChildMessageI18nQuery orderByTitle($order = Criteria::ASC) Order by the title column
 * @method     ChildMessageI18nQuery orderBySubject($order = Criteria::ASC) Order by the subject column
 * @method     ChildMessageI18nQuery orderByTextMessage($order = Criteria::ASC) Order by the text_message column
 * @method     ChildMessageI18nQuery orderByHtmlMessage($order = Criteria::ASC) Order by the html_message column
 *
 * @method     ChildMessageI18nQuery groupById() Group by the id column
 * @method     ChildMessageI18nQuery groupByLocale() Group by the locale column
 * @method     ChildMessageI18nQuery groupByTitle() Group by the title column
 * @method     ChildMessageI18nQuery groupBySubject() Group by the subject column
 * @method     ChildMessageI18nQuery groupByTextMessage() Group by the text_message column
 * @method     ChildMessageI18nQuery groupByHtmlMessage() Group by the html_message column
 *
 * @method     ChildMessageI18nQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildMessageI18nQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildMessageI18nQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildMessageI18nQuery leftJoinMessage($relationAlias = null) Adds a LEFT JOIN clause to the query using the Message relation
 * @method     ChildMessageI18nQuery rightJoinMessage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Message relation
 * @method     ChildMessageI18nQuery innerJoinMessage($relationAlias = null) Adds a INNER JOIN clause to the query using the Message relation
 *
 * @method     ChildMessageI18n findOne(ConnectionInterface $con = null) Return the first ChildMessageI18n matching the query
 * @method     ChildMessageI18n findOneOrCreate(ConnectionInterface $con = null) Return the first ChildMessageI18n matching the query, or a new ChildMessageI18n object populated from the query conditions when no match is found
 *
 * @method     ChildMessageI18n findOneById(int $id) Return the first ChildMessageI18n filtered by the id column
 * @method     ChildMessageI18n findOneByLocale(string $locale) Return the first ChildMessageI18n filtered by the locale column
 * @method     ChildMessageI18n findOneByTitle(string $title) Return the first ChildMessageI18n filtered by the title column
 * @method     ChildMessageI18n findOneBySubject(string $subject) Return the first ChildMessageI18n filtered by the subject column
 * @method     ChildMessageI18n findOneByTextMessage(string $text_message) Return the first ChildMessageI18n filtered by the text_message column
 * @method     ChildMessageI18n findOneByHtmlMessage(string $html_message) Return the first ChildMessageI18n filtered by the html_message column
 *
 * @method     array findById(int $id) Return ChildMessageI18n objects filtered by the id column
 * @method     array findByLocale(string $locale) Return ChildMessageI18n objects filtered by the locale column
 * @method     array findByTitle(string $title) Return ChildMessageI18n objects filtered by the title column
 * @method     array findBySubject(string $subject) Return ChildMessageI18n objects filtered by the subject column
 * @method     array findByTextMessage(string $text_message) Return ChildMessageI18n objects filtered by the text_message column
 * @method     array findByHtmlMessage(string $html_message) Return ChildMessageI18n objects filtered by the html_message column
 *
 */
abstract class MessageI18nQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\MessageI18nQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\MessageI18n', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildMessageI18nQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildMessageI18nQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\MessageI18nQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\MessageI18nQuery();
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
     * $obj = $c->findPk(array(12, 34), $con);
     * </code>
     *
     * @param array[$id, $locale] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildMessageI18n|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = MessageI18nTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(MessageI18nTableMap::DATABASE_NAME);
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
     * @param     ConnectionInterface $con A connection object
     *
     * @return   ChildMessageI18n A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `LOCALE`, `TITLE`, `SUBJECT`, `TEXT_MESSAGE`, `HTML_MESSAGE` FROM `message_i18n` WHERE `ID` = :p0 AND `LOCALE` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildMessageI18n();
            $obj->hydrate($row);
            MessageI18nTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildMessageI18n|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(MessageI18nTableMap::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(MessageI18nTableMap::LOCALE, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(MessageI18nTableMap::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(MessageI18nTableMap::LOCALE, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
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
     * @see       filterByMessage()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(MessageI18nTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(MessageI18nTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(MessageI18nTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the locale column
     *
     * Example usage:
     * <code>
     * $query->filterByLocale('fooValue');   // WHERE locale = 'fooValue'
     * $query->filterByLocale('%fooValue%'); // WHERE locale LIKE '%fooValue%'
     * </code>
     *
     * @param     string $locale The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByLocale($locale = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($locale)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $locale)) {
                $locale = str_replace('*', '%', $locale);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nTableMap::LOCALE, $locale, $comparison);
    }

    /**
     * Filter the query on the title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE title = 'fooValue'
     * $query->filterByTitle('%fooValue%'); // WHERE title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $title)) {
                $title = str_replace('*', '%', $title);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nTableMap::TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the subject column
     *
     * Example usage:
     * <code>
     * $query->filterBySubject('fooValue');   // WHERE subject = 'fooValue'
     * $query->filterBySubject('%fooValue%'); // WHERE subject LIKE '%fooValue%'
     * </code>
     *
     * @param     string $subject The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterBySubject($subject = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($subject)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $subject)) {
                $subject = str_replace('*', '%', $subject);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nTableMap::SUBJECT, $subject, $comparison);
    }

    /**
     * Filter the query on the text_message column
     *
     * Example usage:
     * <code>
     * $query->filterByTextMessage('fooValue');   // WHERE text_message = 'fooValue'
     * $query->filterByTextMessage('%fooValue%'); // WHERE text_message LIKE '%fooValue%'
     * </code>
     *
     * @param     string $textMessage The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByTextMessage($textMessage = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($textMessage)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $textMessage)) {
                $textMessage = str_replace('*', '%', $textMessage);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nTableMap::TEXT_MESSAGE, $textMessage, $comparison);
    }

    /**
     * Filter the query on the html_message column
     *
     * Example usage:
     * <code>
     * $query->filterByHtmlMessage('fooValue');   // WHERE html_message = 'fooValue'
     * $query->filterByHtmlMessage('%fooValue%'); // WHERE html_message LIKE '%fooValue%'
     * </code>
     *
     * @param     string $htmlMessage The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByHtmlMessage($htmlMessage = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($htmlMessage)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $htmlMessage)) {
                $htmlMessage = str_replace('*', '%', $htmlMessage);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nTableMap::HTML_MESSAGE, $htmlMessage, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Message object
     *
     * @param \Thelia\Model\Message|ObjectCollection $message The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function filterByMessage($message, $comparison = null)
    {
        if ($message instanceof \Thelia\Model\Message) {
            return $this
                ->addUsingAlias(MessageI18nTableMap::ID, $message->getId(), $comparison);
        } elseif ($message instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(MessageI18nTableMap::ID, $message->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByMessage() only accepts arguments of type \Thelia\Model\Message or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Message relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function joinMessage($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Message');

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
            $this->addJoinObject($join, 'Message');
        }

        return $this;
    }

    /**
     * Use the Message relation Message object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\MessageQuery A secondary query class using the current class as primary query
     */
    public function useMessageQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinMessage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Message', '\Thelia\Model\MessageQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildMessageI18n $messageI18n Object to remove from the list of results
     *
     * @return ChildMessageI18nQuery The current query, for fluid interface
     */
    public function prune($messageI18n = null)
    {
        if ($messageI18n) {
            $this->addCond('pruneCond0', $this->getAliasedColName(MessageI18nTableMap::ID), $messageI18n->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(MessageI18nTableMap::LOCALE), $messageI18n->getLocale(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the message_i18n table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MessageI18nTableMap::DATABASE_NAME);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            MessageI18nTableMap::clearInstancePool();
            MessageI18nTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildMessageI18n or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildMessageI18n object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public function delete(ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(MessageI18nTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(MessageI18nTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        MessageI18nTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            MessageI18nTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // MessageI18nQuery
