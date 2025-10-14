<?php

namespace b0rner\solr;

use Yii;
use yii\base\Model;
use yii\base\InvalidConfigException;
use yii\data\BaseDataProvider;
use yii\di\Instance;
use Solarium\Core\Query\QueryInterface as SolrQuery;
use b0rner\solr\Connection;
use yii\helpers\Html;

/**
 * This is the ActiveDataProvider for Solr
 *
 * You use this to interact with widgets etc to provide them data.
 *
 * Basic usage of this class would be:
 *
 * $query = Yii::$app->solr->createSelect();
 * $query->setQuery('(alt_subject_mpath:' . $model->path . ' OR alt_subject_mpath:' . $model->path . '.*) AND live:1');
 *
 * new ActiveDataProvider([
 * 		'query' => $query,
 * 		'modelClass' => 'common\models\SolrResult',
 * 		'sort' => [
 * 			'attributes' => [
 * 				'title',
 * 				'sales',
 * 				'score'
 * 			]
 * 		]
 * ]);
 *
 */
class ActiveDataProvider extends BaseDataProvider
{
	/**
	 * @var SolrQuery the query that is used to fetch data models and [[totalCount]]
	 * if it is not explicitly set.
	 */
	public $query;

	/**
	 * @var string|callable the column that is used as the key of the data models.
	 * This can be either a column name, or a callable that returns the key value of a given data model.
	 *
	 * If this is not set, the following rules will be used to determine the keys of the data models:
	 *
	 * - If [[modelClass]] is an [[ActiveRecord]], the primary keys of [[ActiveRecord]] will be used.
	 * - Otherwise, the keys of the [[models]] array will be used.
	 *
	 * @see getKeys()
	 */
	public $key;

	/**
	 * Just like in Yii1 this tells the data provider what class/model to populate
	 */
	public $modelClass;


	public function prepareModels()
	{
		if (!$this->query instanceof ActiveQuery) {
			throw new InvalidConfigException('The "query" property must be an instance of a Solarium Query.');
		}
		if (($pagination = $this->getPagination()) !== false) {
			$pagination->totalCount = $this->getTotalCount();
			$this->query->limit($pagination->getLimit())->offset($pagination->getOffset());
		}

		if (($sort = $this->getSort()) !== false) {
			foreach($sort->getAttributeOrders() as $k => $order){
				$this->query->orderBy([$k => $order == 4 ? 'asc' : 'desc']);
			}
		}

		// This handles a very specific problem:
		// When using spellchecking, even if the search result is zero,
		// some kind of model containing the Spellchecking Object will be returned
		// by a Query.
		// But this model is not suitable for any ActiveDataprovider action (like
		// ListViews) because it lacks any of the normal properties needed.
		// So in this case, if count() is 0 but the model is not empty as it usually is, we just pretend it is
		// so that an ActiveDataProvider can act as normal.
		// This is a weird case, because not finding anything does not mean there is nothing to be
		// given back from the database.
		$models = $this->query->all();
		if (!empty($models) && $this->query->count() == 0) {
			return [];
		}

		return $models;
	}

	public function prepareKeys($models)
	{
		$keys = [];
		if ($this->key !== null) {
			foreach ($models as $model) {
				if (is_string($this->key)) {
					$keys[] = $model[$this->key];
				} else {
					$keys[] = call_user_func($this->key, $model);
				}
			}
			return $keys;
		} else {

			if($this->modelClass){
				/** @var b0rner\solr\ActiveRecord $class */
				$class = $this->query->modelClass;
				$model = new $class;

				if($model instanceof \b0rner\solr\ActiveRecord){

					$pks = $class::primaryKey();
					if (count($pks) === 1) {
						$pk = $pks[0];
						foreach ($models as $model) {
							$keys[] = $model[$pk];
						}
					} else {
						foreach ($models as $model) {
							$kk = [];
							foreach ($pks as $pk) {
								$kk[$pk] = $model[$pk];
							}
							$keys[] = $kk;
						}
					}
					return $keys;
				}
			}
			return array_keys($models);
		}
	}

	public function prepareTotalCount()
	{
		if (!$this->query instanceof ActiveQuery) {
			throw new InvalidConfigException('The "query" property must be an instance of a Solarium Query.');
		}
		$query = clone $this->query;

		return (int) $query->count();
	}

	public function setSort($value)
	{
		parent::setSort($value);
		if (($sort = $this->getSort()) !== false && empty($sort->attributes)) {
			/** @var Model $model */
			$model = new $this->query->modelClass;
			if($model instanceof Model){
				foreach ($model->attributes() as $attribute) {
					$sort->attributes[$attribute] = [
					'asc' => [$attribute => SORT_ASC],
					'desc' => [$attribute => SORT_DESC],
					'label' => $model->getAttributeLabel($attribute),
					];
				}
			}
		}
	}
}
