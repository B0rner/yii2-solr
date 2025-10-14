<?php

namespace b0rner\solr;

use Yii;

use yii\base\InvalidConfigException;
use yii\db\BaseActiveRecord;

use yii\helpers\ArrayHelper;

class ActiveRecord extends BaseActiveRecord
{

    public $_version_;

    /**
     * Highlights
     */
    public $_highlights;

    /**
     * Spellchecks
     */
    public $_spellcheck;

    /**
     * Facets
     */
    public $_facets;

    /**
     * Returns the database connection used by this AR class.
     * By default, the "solr" application component is used as the database connection.
     * You may override this method if you want to use a different database connection.
     * @return Connection the database connection used by this AR class.
     */
    public static function getDb()
    {
        return \Yii::$app->get('solr');
    }

    /**
     * @inheritdoc
     * @return ActiveQuery the newly created [[ActiveQuery]] instance.
     */
    public static function find()
    {
        return Yii::createObject(ActiveQuery::className(), [get_called_class()]);
    }

    /**
     * @inheritdoc
     */
    public static function findOne($condition)
    {
        return static::findByCondition($condition)->one();
    }

    /**
     * @inheritdoc
     */
    public static function findAll($condition)
    {
        return static::findByCondition($condition)->all();
    }

    /**
     * Finds ActiveRecord instance(s) by the given condition.
     * This method is internally called by [[findOne()]] and [[findAll()]].
     * @param mixed $condition please refer to [[findOne()]] for the explanation of this parameter
     * @return ActiveQueryInterface the newly created [[ActiveQueryInterface|ActiveQuery]] instance.
     * @throws InvalidConfigException if there is no primary key defined.
     * @internal
     */
    protected static function findByCondition($condition)
    {
        $query = static::find();

        // if a $conditon like ['field' => 'value'] is given, nothing has to be
        // changed
        if (ArrayHelper::isAssociative($condition)) {
            return $query->andWhere($condition);
        }

        $pk = static::primaryKey()[0];

        // assuming, $condition is the primaryKey as a string
        if(is_string($condition)) {
            $condition = [$pk => $condition];
        }

        // assuming $condition gives multiple possible primary keys
        // e.g [1, 3, 4]
        if (ArrayHelper::isIndexed($condition)) {
            $c = [];
            foreach($condition as $pk_value) {
                $c[$pk][] = $pk_value;
            }

            $condition = $c;
        }

        return $query->orWhere($condition);

    }

    /**
     * @return array|null A list of arrays with highlighted excerpts indexed by field names.
     */
    public function getHighlights()
    {
        return $this->_highlights;
    }

    /**
     * @return array|null Spellcheck Results
     */
    public function getSpellcheck()
    {
        return $this->_spellcheck;
    }

    /**
     * @return array|null Facets Results
     */
    public function getFacets()
    {
        return $this->_facets;
    }

    /**
     * Returns the list of all attribute names of the model.
     *
     * This method must be overridden by child classes to define available attributes.
     *
     * Attributes are names of fields of the corresponding SOLR document.
     *
     * @return string[] list of attribute names.
     * @throws \yii\base\InvalidConfigException if not overridden in a child class.
     */
    public function attributes()
    {
        throw new InvalidConfigException('The attributes() method of SOLR ActiveRecord has to be implemented by child classes.');
    }

    /**
     * Inserts a row into the associated database table using the attribute values of this record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeValidate()]] when `$runValidation` is `true`. If [[beforeValidate()]]
     *    returns `false`, the rest of the steps will be skipped;
     * 2. call [[afterValidate()]] when `$runValidation` is `true`. If validation
     *    failed, the rest of the steps will be skipped;
     * 3. call [[beforeSave()]]. If [[beforeSave()]] returns `false`,
     *    the rest of the steps will be skipped;
     * 4. insert the record into database. If this fails, it will skip the rest of the steps;
     * 5. call [[afterSave()]];
     *
     * Only the [[dirtyAttributes|changed attribute values]] will be inserted into database.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer;
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param bool $runValidation whether to perform validation (calling [[validate()]])
     * before saving the record. Defaults to `true`. If the validation fails, the record
     * will not be saved to the database and this method will return `false`.
     * @param array|null $attributes list of attributes that need to be saved. Defaults to `null`,
     * meaning all attributes that are loaded from DB will be saved.
     * @return Object  Solarium Result Object
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            Yii::info('Model not inserted due to validation error.', __METHOD__);
            return false;
        }

        if (!$this->beforeSave(true)) {
            return false;
        }

        $uniqueId = $this->primaryKey()[0];
        $values = $this->getDirtyAttributes($attributes);

        $result = static::getDb()->createCommand()->insert($values, $uniqueId);

        $changedAttributes = array_fill_keys(array_keys($values), null);

        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return $result;
    }

    /**
     * Just for convinience
     */
    public function update($runValidation = true, $attributes = null)
    {
        return $this->insert($runValidation = true, $attributes = null);
    }

    /**
     * Just for convinience as well
     */
    public function save($runValidation = true, $attributes = null)
    {
        return $this->insert($runValidation = true, $attributes = null);
    }

    /**
     * Deletes a document (record)
     */
    public function delete()
    {
        $uniqueKeyFieldName = $this->primaryKey()[0];
        return static::getDb()->createCommand()->delete($uniqueKeyFieldName, $this->$uniqueKeyFieldName);
    }

    /**
     * This method defines the attribute that uniquely identifies a record.
     * This method has to be overwritten in the child class.
     * It has to be set corresponding to the SOLR configuration parameter <uniqueKey>
     *
     * @return string[] array of primary key attributes. Only the first element of the array will be used.
     * @throws \yii\base\InvalidConfigException if not overridden in a child class.
     */
    public static function primaryKey()
    {
        throw new InvalidConfigException('The primaryKey() method of SOLR ActiveRecord has to be implemented by child classes.');
    }

}