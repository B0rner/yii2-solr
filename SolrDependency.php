<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 *
 * @author ralf.matulat@bundestag.de
 */

namespace b0rner\solr;

use Yii;

use yii\base\InvalidConfigException;
use yii\caching\Dependency;
use yii\di\Instance;


class SolrDependency extends Dependency
{

    /**
     * @var string the application component ID of the DB connection.
     */
    public $db = 'solr';
    /**
     * @var Instance of Query - the SOLR query whose result is used to determine if the dependency has been changed.
     * Only the first row of the query result will be used.
     */
    public $query;

    /**
     * Generates the data needed to determine if dependency has been changed.
     * This method returns the value of the global state.
     * @param CacheInterface $cache the cache component that is currently evaluating this dependency
     * @return mixed the data needed to determine if dependency has been changed.
     * @throws InvalidConfigException if [[db]] is not a valid application component ID
     */
    protected function generateDependencyData($cache)
    {
        /* @var $db Connection */
        $db = Instance::ensure($this->db, Connection::className());
        if ($this->query === null || !($this->query instanceof Query)) {
            throw new InvalidConfigException('SolrDependency::query must be set and an Instance of Query.');
        }

        return $this->query->count();
    }

}