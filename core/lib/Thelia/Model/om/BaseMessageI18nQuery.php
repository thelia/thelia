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
use Thelia\Model\Message;
use Thelia\Model\MessageI18n;
use Thelia\Model\MessageI18nPeer;
use Thelia\Model\MessageI18nQuery;

/**
 * Base class that represents a query for the 'message_i18n' table.
 *
 *
 *
 * @method MessageI18nQuery orderById($order = Criteria::ASC) Order by the id column
 * @method MessageI18nQuery orderByLocale($order = Criteria::ASC) Order by the locale column
 * @method MessageI18nQuery orderByTitle($order = Criteria::ASC) Order by the title column
 * @method MessageI18nQuery orderByDescription($order = Criteria::ASC) Order by the description column
 * @method MessageI18nQuery orderByDescriptionHtml($order = Criteria::ASC) Order by the description_html column
 *
 * @method MessageI18nQuery groupById() Group by the id column
 * @method MessageI18nQuery groupByLocale() Group by the locale column
 * @method MessageI18nQuery groupByTitle() Group by the title column
 * @method MessageI18nQuery groupByDescription() Group by the description column
 * @method MessageI18nQuery groupByDescriptionHtml() Group by the description_html column
 *
 * @method MessageI18nQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method MessageI18nQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method MessageI18nQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method MessageI18nQuery leftJoinMessage($relationAlias = null) Adds a LEFT JOIN clause to the query using the Message relation
 * @method MessageI18nQuery rightJoinMessage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Message relation
 * @method MessageI18nQuery innerJoinMessage($relationAlias = null) Adds a INNER JOIN clause to the query using the Message relation
 *
 * @method MessageI18n findOne(PropelPDO $con = null) Return the first MessageI18n matching the query
 * @method MessageI18n findOneOrCreate(PropelPDO $con = null) Return the first MessageI18n matching the query, or a new MessageI18n object populated from the query conditions when no match is found
 *
 * @method MessageI18n findOneById(int $id) Return the first MessageI18n filtered by the id column
 * @method MessageI18n findOneByLocale(string $locale) Return the first MessageI18n filtered by the locale column
 * @method MessageI18n findOneByTitle(string $title) Return the first MessageI18n filtered by the title column
 * @method MessageI18n findOneByDescription(string $description) Return the first MessageI18n filtered by the description column
 * @method MessageI18n findOneByDescriptionHtml(string $description_html) Return the first MessageI18n filtered by the description_html column
 *
 * @method array findById(int $id) Return MessageI18n objects filtered by the id column
 * @method array findByLocale(string $locale) Return MessageI18n objects filtered by the locale column
 * @method array findByTitle(string $title) Return MessageI18n objects filtered by the title column
 * @method array findByDescription(string $description) Return MessageI18n objects filtered by the description column
 * @method array findByDescriptionHtml(string $description_html) Return MessageI18n objects filtered by the description_html column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseMessageI18nQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseMessageI18nQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\MessageI18n', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new MessageI18nQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     MessageI18nQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return MessageI18nQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof MessageI18nQuery) {
            return $criteria;
        }
        $query = new MessageI18nQuery();
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
     * @param array $key Primary key to use for the query
                         A Primary key composition: [$id, $locale]
     * @param     PropelPDO $con an optional connection object
     *
     * @return   MessageI18n|MessageI18n[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = MessageI18nPeer::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(MessageI18nPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   MessageI18n A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `LOCALE`, `TITLE`, `DESCRIPTION`, `DESCRIPTION_HTML` FROM `message_i18n` WHERE `ID` = :p0 AND `LOCALE` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new MessageI18n();
            $obj->hydrate($row);
            MessageI18nPeer::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return MessageI18n|MessageI18n[]|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return PropelObjectCollection|MessageI18n[]|mixed the list of results, formatted by the current formatter
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
     * @return MessageI18nQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(MessageI18nPeer::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(MessageI18nPeer::LOCALE, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return MessageI18nQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(MessageI18nPeer::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(MessageI18nPeer::LOCALE, $key[1], Criteria::EQUAL);
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
     * @return MessageI18nQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(MessageI18nPeer::ID, $id, $comparison);
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
     * @return MessageI18nQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageI18nPeer::LOCALE, $locale, $comparison);
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
     * @return MessageI18nQuery The current query, for fluid interface
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

        return $this->addUsingAlias(MessageI18nPeer::TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE description = 'fooValue'
     * $query->filterByDescription('%fooValue%'); // WHERE description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return MessageI18nQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $description)) {
                $description = str_replace('*', '%', $description);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nPeer::DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query on the description_html column
     *
     * Example usage:
     * <code>
     * $query->filterByDescriptionHtml('fooValue');   // WHERE description_html = 'fooValue'
     * $query->filterByDescriptionHtml('%fooValue%'); // WHERE description_html LIKE '%fooValue%'
     * </code>
     *
     * @param     string $descriptionHtml The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return MessageI18nQuery The current query, for fluid interface
     */
    public function filterByDescriptionHtml($descriptionHtml = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($descriptionHtml)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $descriptionHtml)) {
                $descriptionHtml = str_replace('*', '%', $descriptionHtml);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(MessageI18nPeer::DESCRIPTION_HTML, $descriptionHtml, $comparison);
    }

    /**
     * Filter the query by a related Message object
     *
     * @param   Message|PropelObjectCollection $message The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   MessageI18nQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByMessage($message, $comparison = null)
    {
        if ($message instanceof Message) {
            return $this
                ->addUsingAlias(MessageI18nPeer::ID, $message->getId(), $comparison);
        } elseif ($message instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(MessageI18nPeer::ID, $message->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByMessage() only accepts arguments of type Message or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Message relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return MessageI18nQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * @param   MessageI18n $messageI18n Object to remove from the list of results
     *
     * @return MessageI18nQuery The current query, for fluid interface
     */
    public function prune($messageI18n = null)
    {
        if ($messageI18n) {
            $this->addCond('pruneCond0', $this->getAliasedColName(MessageI18nPeer::ID), $messageI18n->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(MessageI18nPeer::LOCALE), $messageI18n->getLocale(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

}
