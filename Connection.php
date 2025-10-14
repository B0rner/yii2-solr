<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 *
 */

namespace b0rner\solr;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidArgumentException;

use Solarium\Client;

/**
 * SOLR Connection is used to connect to an SOLR Server
 *
 * @property-read bool $isActive Whether the DB connection is established.
 * @property-read QueryBuilder $queryBuilder
 */
class Connection extends Component
{

    /**
     * @event Event an event that is triggered after a DB connection is established
     */
    const EVENT_AFTER_OPEN = 'afterOpen';

    /**
     * @var array Solarium client options
     */
    public $options = [];

    /**
     * @var bool is there an active SOLR client?
     */
    public $activeSolr = false;

    /**
     * @var array Connection options
     */

    /**
     * @ resource SOLR client returned by Solarium
     */
    public $client;

    /**
     * Returns a value indicating whether the DB connection is established.
     * @return bool whether the DB connection is established
     */
    public function getIsActive()
    {
        return $this->activeSolr;
    }

    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     * @throws Exception if connection fails
     */
    public function open()
    {
        if ($this->activeSolr) {
            return;
        }
        if (empty($this->options)) {
            throw new InvalidConfigException('SOLR Component options not set.');
        }

		$adapter = new \Solarium\Core\Client\Adapter\Http();

        if (array_key_exists('timeout', $this->options) && is_int($this->options['timeout'])) {
            $adapter->setTimeout($this->options['timeout']);
        }

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$this->client = new Client($adapter, $dispatcher, $this->options);

		$this->activeSolr = true;

        $this->initConnection();
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    public function close()
    {
        if ($this->activeSolr == false) {
            return;
        }

        $this->activeSolr = false;
    }


    /**
     * Creates a command for execution.
     * @param sarray $config the configuration for the Command class
     * @return Command the DB command
     */
    public function createCommand($phrase = null, $config = [])
    {
        $this->open();
        $config['phrase'] = $phrase;
        $config['db'] = $this;
        $command = new Command($config);

        return $command;
    }

    /**
     * Creates new query builder instance
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * Initializes the DB connection.
     * This method is invoked right after the DB connection is established.
     * The default implementation triggers an [[EVENT_AFTER_OPEN]] event.
     */
    protected function initConnection()
    {
        $this->trigger(self::EVENT_AFTER_OPEN);
    }

}
