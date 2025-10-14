yii2-solr Extension
===

A Yii2 Solr Extension built on top of [Solarium 6](https://solarium.readthedocs.io/en/stable/getting-started/) and PHP 8.
This extension is based of the yii2-solr from Sammaye (https://github.com/Sammaye/yii2-solr), which unfortunately is not being developed further. Thanks Sammaye for the important work so far that has made it possible to use Solr elegantly in Yii2.

Unlike Sammaye's original extension, this version now provides ActiveRecord implementation for Solr data. This brings Solr document handling in line with Yii2's standard database ActiveRecord patterns, making it significantly easier and more intuitive to work with Solr. The ActiveRecord implementation provides familiar Yii2 patterns (`find()`, `findOne()`, `save()`, `delete()`, etc.), eliminates boilerplate code, and includes full ActiveDataProvider and ActiveFixture support.

This code is provided as is, and no guarantee is given, also no warranty/guarantee, that this code will preform in the desired way.There will be no guarantee that there will be patches for this software in the future.

This extension tries to mimic Yii's database bahavior as close as possible. As Yii provides different ways to query SQL databases, this extension does the same with SOLR. Nevertheless SQL databases and SOLR are different in many ways - so there have to be compromises.

By no means this extension covers all possibilities of SOLR nor of Solarium.

Installation
===

Composer ToDo

Setup
===

First one has to add the extension to the Yii2 configuration under `components`:

```php
        'solr' => [
            'class' => 'b0rner\solr\Connection',
            'options' => [
                'endpoint' => [
                    'solr1' => [
                        'host' => 'solr',
                        'port' => '8983',
                        'path' => '/',
                        'collection' => 'my_collection'
                    ]
                ]
            ]
        ],
```

The `options` part of the configuration is a one-to-one match to Solariums own constructor and options. See [documentation](https://solarium.readthedocs.io/en/stable/client-and-adapters/)


Working with Query Class
===

A simple way to query the SOLR index is the Query Class.
It provides an easy way to get documents out of an index:


```php
<?php

use b0rner\solr\Query;

$query = new Query;
$query->select('text, title, tags');
$query->q('foo OR bar')->offset(101);
$query->orderBy(['date' => 'desc']);
$query->where([
	'tag' => ['nature'],
	'author' => ['John Doe']
	], 'and');
$query->limit(10);

$result = $query->all();
```

Working with a connection
===

This next method is one of the least useful possibilities querying a SOLR index, but because it can be done it has to be mentioned:

```php

$connection = new \b0rner\solr\Connection([
	'options' => [
		'endpoint' => [
			'solr1' => [
				'host' => 'solr',
				'port' => '8983',
				'path' => '/',
				'collection' => 'news'
			]
		]
	]
]);

$results = $connection->createCommand('foo AND bar')->queryAll();

foreach ($results as $result) {
	var_dump($result->title);
}

```

Working with Active Record
===

To work with Active Record you have to create a Model, for example `app/models/News.php`:

```php
<?php

namespace app\models;

use b0rner\solr\ActiveRecord;

class News extends ActiveRecord
{
	// ...
}
```

There are at least two class methods that have to be implemented - `attributes()` and `primaryKey()`.
The first has to be implemented in all Yii models and returns an array with all the names of needed Class proprties (aka columns in
SQL world or fields in SOLR universe):

```php

public function attributes() {
	return ['title', 'text', 'tags', 'date', 'author']
}
```

In SOLR configuration one field will be defined as unique, storing an identifier. It is provided under `<uniqueId>`.
The name of this field must be provided by a Class method called `primaryKey()`:

```php
    /**
     * This method defines the attribute that uniquely identifies a record.
     * This method has to be overwritten in the child class.
     * It has to be set corresponding to the SOLR configuration parameter <uniqueKey>
     *
     * @return array array of primary key attributes. Only the first element of the array will be used.
     * @throws \yii\base\InvalidConfigException if not overridden in a child class.
     */
    public static function primaryKey()
    {
        return ['id'];
    }

```

Having those two functions in place, you are ready to go.

Example 1
====

`findOne()` and `findAll()` can be used in 3 different ways:

```php

// giving a string assumes that a uniqueKey is provided. It will search for that id, not more.
$news = News::findOne('d4cfb176f7f9d3f7bf3523ec9832f812');

// giving an associative array will search like `fieldname:value`, multiple key-value pairs will be combined with AND
$news = News::findAll(['title' => 'foo', 'author' => 'John Doe']);

// giving an indexed array assumes, that multiple uniqueKeys are provided. They will be searched with OR
$news = News::findAll(['123', '456', '789']);
```

Example 2
====

Working with Active Query.
When using a Model that extends from ActiveRecord, using `find()` returns an ActiveQuery instance. That is true while working with Yii2's own ORM as well as working
with this extension and SOLR

That said and because ActiveQuery just extends the Query Class itself you can use every `b0rner\solr\Query` method for finding, sorting, limiting documents in SOLR:

```php

// searching just for a single term and just return one document
$result = News::find()
    ->q('berlin')
    ->one();

// search for "berlin" but add a filter query for the author.
$result = News::find()
    ->q('berlin')
    ->where(['author' => 'John Doe'])
    ->one();

// search for "berlin" and find news whether John Doe OR Jane Doe are author.
$result = News::find()
    ->q('berlin')
    ->where(['author' => ['John Doe', 'Jane Doe']])
    ->one();

// same, but return 5 documents instead of just one.
$result = News::find()
    ->q('berlin')
    ->where(['author' => ['John Doe', 'Jane Doe']])
    ->limit(5)
    ->all();

// this does exactly the same but just uses SOLR wording
$result = News::find()
    ->q('berlin')
    ->fq(['author' => ['John Doe', 'Jane Doe']])
    ->rows(5)
    ->all();

// skip the first 20 documents
$result = News::find()
    ->q('berlin')
    ->where(['author' => ['John Doe', 'Jane Doe']])
    ->limit(5)
    ->offset(20)
    ->all();

// order by date field in ascending order.
$result = News::find()
    ->q('berlin')
    ->where(['author' => ['John Doe', 'Jane Doe']])
    ->limit(5)
    ->offset(20)
    ->orderBy('date, desc')
    ->all();

// same - notice the array as orderBy parameter
$result = News::find()
    ->q('berlin')
    ->where(['author' => ['John Doe', 'Jane Doe']])
    ->limit(5)
    ->offset(20)
    ->orderBy(['date' => 'desc'])
    ->all();
```

Note: When omitting the `all()` or `one()` methods, `$result` can be feed into an ActiveDataProvider instance (see below).

Insert, Update, Save, Delete
===

Basic CRUD operations are implemented, but this is not a relational database. Usually you've got some external process to feed documents into your SOLR
index. So you might find yourself not needing anything of this.

Deleting
====

There are two ways:

First, using `createCommand`
```php

// deleting one specific document
$db = News::getDb()->createCommand();
$db->delete('id', 'af90a227-e677-4899-8362-ac7443969329');

// deleting ALL (!) documents authored by John Doe
$db = News::getDb()->createCommand();
$db->delete('author', 'John Doe');

// Doing the same thing just with the `solr` component:
$result = \Yii::$app->solr->createCommand()->delete('author', 'John Doe');
```

Second, using the ActiveRecord instance
```php

$news = News::findOne('8e100f8c-6ff1-4312-a84f-2f07a8eb7364');
$news->delete();
```

Insert, Update, Save
====

All three methods are actually the same thing, doing exactly the same, are even the same single method!
As long, as a document has an in SOLR existing uniqueKey value, that specific document will be updated.
If a provided uniqueKey is not existing in the SOLR index, a new document will be added to the index.
If no uniqueKey is provided, a new document will be added as well but with a SOLR generated uniqueKey!

```php

// Because no uniqueKey is given, a new document will be added, with a SOLR generated value
$news = new News;
$news->title = "foo";
$news->text = "my text";
$news->author = "Jane Doe";
$result = $news->insert();

var_dump($result);

// Assuming that the given `id` property is unkown to the SOLR index, this creates a new document
// as well
$news = new News;
$news->title = "foo";
$news->text = "my text";
$news->author = "Jane Doe";
$news->id = "12345678";
$result = $news->insert();

var_dump($result);

// And now we are updating the formerly inserted document, because the `id`
// already exists in the index.
// Note that we are using `insert()` for updating.
$news = new News;
$news->title = "foo bar";
$news->text = "my new text";
$news->id = "12345678";
$result = $news->insert();

```

CAUTION: It is likely that you implement something in `afterFind()`, like converting a UTC datefield to some
local readable time string.
Thats why, when doing something like this, stuff can fall apart:

```php
$news = News::findOne('4db5ef81-8aa9-4f2e-8553-1ee32a375bfb');
$news->prio = 5;
$news->save();
```

Using `findOne()` triggers `afterFind()`. Because SOLR stores dates in UTC, you will usually transform a date
to something readable:

```php

public function afterFind()
{
    $this->date = \Yii::$app->formatter->format($this->date, 'date');
}
```

Now `$news->date` has a different value or format than stored in SOLR. When now calling `save()` SOLR will throw an exception because
of the wrong timeformat.
You have to handle such things in `beforeSave()` or otherwise.

Highlighting
===

One common feature is highlighting of terms. So you can do something like this:

```php

$result = News::find()
    ->q('berlin')
    ->hl()
    ->hlFl('text, title')
    ->hlFragsize(100)
    ->hlSnippets(2)
```

The term `berlin` - if found in fields `text` and/or `title` - will be highlighted. Those highlighted
snippets are accessable through $model->getHighlights(). It returns an array with

```php

[
    $fieldname => $highlighted_snippet,
    $another_fieldname => $another_snippet
]
```

This can be used in `afterFind()` like this:

```php
if (!empty($this->getHighlights())) {
    if (array_key_exists('text', $this->getHighlights())) {
        $this->text = $this->getHighlights()['text'][0];
    }

    if (array_key_exists('title', $this->getHighlights())) {
        $this->title = $this->getHighlights()['title'][0];
    }
}
```

NOTE: Using `hl()` to activate highlighting is not necessary because using one of the `hl` functions implicitly
activates it.

DisMax and EDisMax Queryhandler
===

When triggered, this extension just uses the EDisMax Query handler. This is because everything the DisMax Handler provides
is in the EDisMax handler as well. So the later is used.

You may activate the EDisMax Handler like that:

```php

$query = $News->find()
              ->edismax()
              ->q('berlin')
```

Using EDisMax with QueryFields for boostig field `title`:

```php

$query = $News->find()
              ->edismax()
              ->q('berlin')
              ->qf('text title^5')
```

The following functions regarding boosting and EDisMax are implemented:
* `bf()`
* `bq()`
* `boost()`
* `qf()` for boosting specific fields by default

ActiveDataProvider
===

This extension provides an ActiveDataProvider:

```php
use b0rner\solr\ActiveDataProvider;

// ...

    $query = News::find()
        ->q('berlin')
        ->where(['author' => ['John Doe', 'Jane Doe']]);

    $dataProvider = new ActiveDataProvider([
        'query' => $query,
        'pagination' => ['pageSize' => 10]
    ]);

    return $this->render('news_view', [
        'dataProvider' => $dataProvider,
    ]);

```

Grouping
===
Solr grouping feature is provided by this extension. Useing the News class example from above (`class News extends ActiveRecord`):

```php
$news = new News();

// group by field. This example groups by field 'ressorts', that contains a ressort-number

$resultA = $news->find()
            ->edismax()
            ->q('*:*') 
            ->limit(100) // max. number of results to group on. Has no effect on groupSetFormat('grouped')
            ->fq(...)  // filter documents before grouping the result
            ->fl(...) // define the fields that should part of the grouped documents
            ->rows(2)  // define the numer of groups, not the numer of documents per group
            ->group()  // enable grouping
            ->groupSetLimit(5) // set the numer of documents per group, if format=grouped. Has no effect on groupSetFormat('simple'). 
            ->groupAddField('ressort') // set the field for grouping, use comma to seperate multiple fields
            ->groupSetCachepercentage(0) // set solrs group.cache.percent. 0> will not result in better performance in some cases
            ->grouping();  // returning the group result set. Don't use ->one() or ->all()
                           // because solr does not return a result set by default


// $resultA contains the solarium grouping component, including the result of 2 groups with 5 documents per group
###

// group by different queries

$resultB = $news->find()
            ->edismax()
            ->q('*:*') 
            ->fq(...)  // filter documents before grouping the result
            ->fl(...) // define the fields that should part of the grouped documents
            ->limit(150)
            ->rows(2)  // will be ignored, `groupAddQuery` defindes the number of groups
            ->group()  // enable grouping
            ->groupSetLimit(20) // set the numer of documents per group, if format = grouped
            ->groupSetOffset(4) // getting documents 5-25 for each group
            ->groupNumberOfGroup(true) // returning the number og groups in the result set, Solarium default = false
            ->groupAddQuery('berlin') // adding a guery to group on
            ->groupAddQuery('paris') // adding a guery to group on
            ->groupSetSort('last_modified desc') // order documents within a single group (by last_modified)
            ->groupSetFormat('simple') // 
            ->grouping();  // returning the group result set. Don't use ->one() or ->all()
                           // because solr does not return a result set by default

// $resultB contains the solarium grouping component, including 2 groups ('berlin' and 'paris') with 150 docuemnts per group


```

Last
===
To get a list of all suppoerte Solr parameters see `Query-php`

Credits
===

Co-authored-by: rmatulat <https://github.com/rmatulat>
Special thanks to @rmatulat for contributing the majority of ActiveRecord, ActiveDataProvider, ActiveFixtures.
