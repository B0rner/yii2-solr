Creating a Model
===

```php
<?php

namespace app\models;

use Yii;

use app\components\SharedMethods;
use b0rner\solr\ActiveRecord;

class SolrNews extends ActiveRecord {

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
        return ['uid'];
    }

}
```

Finding Stuff
===

```php

$solrNews = new SolrNews();

$query = $solrNews->find()
                  ->edismax()
                  ->q('foo bar')
                  ->hlFl('text, title')
                  ->hlFragsize(200)
                  ->hlSnippets(1)
                  ->hlTagPre('[Token]')
                  ->hlTagPost('[/Token]')
                  ->where(['date' => '[ * TO NOW ]']);

$dataProvider = new SolrDataProvider([
    'query' => $query,
    'modelClass' => 'app\models\SolrNews',
    'pagination' => ['pageSize' => $pagesize]
]);

```

Facetting
===

See also: https://solarium.readthedocs.io/en/stable/queries/select-query/building-a-select-query/components/facetset-component/facet-field/#

```php

$solrNews = new SolrNews();

$query = $solrNews->find()
                  ->q('habeck')
                  ->facetCreateFacetRange('FOO')
                  ->facetSetSort('index')
                  ->facetSetField('iddatum')
                  ->facetSetStart('NOW-6MONTH')
                  ->facetSetEnd('NOW')
                  ->facetSetGap('+1DAY');

$facets = $query->one()->getFacets();
$facet = $facets->getFacet('FOO');

```