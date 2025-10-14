<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace b0rner\solr;

use Yii;
use yii\base\InvalidConfigException;
use yii\test\BaseActiveFixture;

/**
 * ActiveFixture represents a fixture for testing backed up by an [[modelClass|ActiveRecord class]] or an elastic search index.
 *
 * [[modelClass]] must be set. You should also provide fixture data in the file
 * specified by [[dataFile]] or overriding [[getData()]] if you want to use code to generate the fixture data.
 *
 * When the fixture is being loaded, it will first call [[deleteAllDocuments()]] to remove any existing data in the index.
 * It will then populate the documents with the data returned by [[getData()]].
 *
 * After the fixture is loaded, you can access the loaded data via the [[data]] property. If you set [[modelClass]],
 * you will also be able to retrieve an instance of [[modelClass]] with the populated data via [[getModel()]].
 *
 */
class ActiveFixture extends BaseActiveFixture
{

    /**
     * @var Connection|string the DB connection object or the application component ID of the DB connection.
     * After the DbFixture object is created, if you want to change this property, you should only assign it
     * with a DB connection object.
     */
    public $db = 'solr';

    /**
     * @var string|boolean the file path or path alias of the data file that contains the fixture data
     * to be returned by [[getData()]]. If this is not set, it will default to `FixturePath/data/Index/Type.php`,
     * where `FixturePath` stands for the directory containing this fixture class, `Index` stands for the elasticsearch [[index]] name
     * and `Type` stands for the [[type]] associated with this fixture.
     * You can set this property to be false to prevent loading any data.
     */
    public $dataFile;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (!isset($this->modelClass) && (!isset($this->core))) {
            throw new InvalidConfigException('Either "modelClass" and "core" must be set.');
        }
    }

    /**
     * Loads the fixture.
     *
     * The default implementation will first clean up the index by calling [[deleteAllDocuments()]].
     * It will then populate the core with the data returned by [[getData()]].
     *
     * If you override this method, you should consider calling the parent implementation
     * so that the data returned by [[getData()]] can be populated into the index.
     */
    public function load()
    {
        $this->data = [];

        $modelClass = $this->modelClass;
        $idField = $modelClass::primaryKey()[0];

        $this->deleteAllDocuments($idField);

        foreach ($this->getData() as $alias => $row) {
            try {
                $response = $this->db->createCommand()->insert($row);
            } catch(Exception $e) {
                throw new \yii\base\Exception("Failed to insert fixture data \"$alias\": " . $e->getMessage() . "\n" . print_r($e->errorInfo, true), $e->getCode(), $e);
            }
            $this->data[$alias] = $row;
        }
    }

    /**
     * Returns the fixture data.
     *
     * The default implementation will try to return the fixture data by including the external file specified by [[dataFile]].
     * The file should return an array of data rows (column name => column value), each corresponding to a row in the index.
     *
     * If the data file does not exist, an empty array will be returned.
     *
     * @return array the data rows to be inserted into the database index.
     */
    protected function getData()
    {
        if ($this->dataFile === null) {
            $class = new \ReflectionClass($this);
            $dataFile = dirname($class->getFileName()) . "/data/{$this->index}/{$this->type}.php";
            return is_file($dataFile) ? require($dataFile) : [];
        } else {
            return parent::getData();
        }
    }

    /**
     * Delete all documents
     */
    public function deleteAllDocuments(string $idField)
    {
    	$this->db->createCommand()->delete($idField, '*');
    }

}
