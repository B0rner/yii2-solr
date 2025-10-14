<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 *
 * @author ralf.matulat@bundestag.de
 */
namespace b0rner\solr;


class ActiveQuery extends Query
{

    public $config;

    /**
     * @var string the name of the ActiveRecord class.
     */
    public $modelClass;

    /**
     * @var bool whether to return each record as an array. If false (default), an object
     * of [[modelClass]] will be created to represent each record.
     */
    public $asArray;

    /**
     * Constructor.
     * @param string $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        $this->config = $config;
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an [[EVENT_INIT]] event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * Creates a DB command that can be used to execute this query.
     * @param Connection|null $db the DB connection used to create the DB command.
     * If `null`, the DB connection returned by [[modelClass]] will be used.
     * @return Command the created DB command instance.
     */
    public function createCommand($db = null)
    {
        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;
        if ($db === null) {
            $db = $modelClass::getDb();
        }

        $commandConfig = $db->getQueryBuilder()->build($this);

        $command = $db->createCommand(null, $commandConfig);

        return $command;
    }

    /**
     * Converts found rows into model instances.
     * @param array $rows
     * @return array|ActiveRecord[]
     */
    protected function createModels($rows)
    {
        if ($this->asArray) {
            return $rows;
        } else {
            $models = [];
            /* @var $class ActiveRecord */
            $class = $this->modelClass;
            foreach ($rows as $row) {
                $model = $class::instantiate($row);
                $modelClass = get_class($model);
                $modelClass::populateRecord($model, $row);
                $model->afterFind();
                $models[] = $model;
            }
            return $models;
        }
    }

    /**
     * Sets the [[asArray]] property.
     * @param bool $value whether to return the query results in terms of arrays instead of Active Records.
     * @return $this the query object itself
     */
    public function asArray($value = true)
    {
        $this->asArray = $value;
        return $this;
    }

    /**
     * Executes query and returns all results as an array.
     * @param Connection|null $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return array|ActiveRecord[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all($db = null)
    {
        if (($results = parent::all($db)) === false) {
            return [];
        }
        if ($this->asArray) {
            return $results;
        } else {
            return $this->createModels($results);
        }
    }

    /**
     * Executes query and returns a single row of result.
     * @param Connection $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting of [[asArray]],
     * the query result may be either an array or an ActiveRecord object. Null will be returned
     * if the query results in nothing.
     */
    public function one($db = null)
    {
        if (($result = parent::one($db)) === false) {
            return null;
        }

        if ($this->asArray) {
            return $result;
        } else {
            /* @var $class ActiveRecord */
            $class = $this->modelClass;
            $model = $class::instantiate($result);
            $class = get_class($model);
            $class::populateRecord($model, $result);
            $model->afterFind();
            return $model;
        }
    }

    /**
     * Executes a group query and returns all results as an arrays of
     * documents for each group.
     * @param Connection|null $db the DB connection used to create the DB command.
     * If null, the DB connection returned by [[modelClass]] will be used.
     * @return array|ActiveRecord[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function grouping($db = null)
    {
        if (($results = parent::grouping($db)) === false) {
            return [];
        }
        if ($this->asArray) {
            // returning default solarium result objects, not an
            // assoziative-array-only structure
            return $results;
        } else {
            $array_of_groups = [];
            foreach ($results as $group_name => $group_docs) {
                $array_of_groups[$group_name] = $this->createModels($group_docs);
            }
            return $array_of_groups;
        }
    }

}