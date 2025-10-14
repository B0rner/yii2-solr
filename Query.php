<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace b0rner\solr;

use Yii;
use yii\base\InvalidArgumentException;
use yii\base\Component;


/**
 * Query represents a search against a SOLR application server.
 *
 * Query provides a set of methods to facilitate the specification of different clauses
 * in a Solr search. These methods can be chained together.
 *
 * By calling [[createCommand()]], we can get a [[Command]] instance which can be further
 * used to perform/execute the SOLR query against a SOLR Host.
 *
 * This class tries to emulate the SQL behavior as close as possible.
 *
 */

class Query extends Component
{

    /**
     * @var array|null the columns (aka fields) being selected. For example, `['id', 'name']`.
     * If not set, it means selecting all columns.
     * @see https://solr.apache.org/guide/7_6/common-query-parameters.html#fl-field-list-parameter
     * @see select()
     */
    public $select = '*';

    /**
     * @var int|bool|null the default number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * Use a negative number to indicate that query cache should not be used.
     * Use boolean `true` to indicate that [[Connection::queryCacheDuration]] should be used.
     * @see cache()
     * @since 2.0.14
     */
    public $queryCacheDuration;
    /**
     * @var \yii\caching\Dependency|null the dependency to be associated with the cached query result for this query
     * @see cache()
     * @since 2.0.14
     */
    public $queryCacheDependency;

    /**
     * @var string the search phrase (aka query, aka q=) itself, like "color: red AND size:large"
     */
    public $q;

    /**
     * @var array query condition. This refers to the Filter Query in a SOLR search.
     * Multiple Filter Queries can be provided by chaining ->where()
     * @see where() for valid syntax on specifying this value.
     */
    public $where = [];
    /**
     * @var int|null maximum number of records to be returned.
     * @see https://solr.apache.org/guide/7_6/common-query-parameters.html#rows-parameter
     */
    public $limit;

    /**
     * @var int|null zero-based offset from where the records are to be returned, see start parameter in SOLR queries.
     * @see https://solr.apache.org/guide/7_6/common-query-parameters.html#start-parameter
     * If not set or 0, it means starting from the beginning.
     */
    public $offset;

    /**
     * @var array|null how to sort the query results. This is used to construct the sort parameter.
     * @see https://solr.apache.org/guide/7_6/common-query-parameters.html#sort-parameter
     */
    public $orderBy;

    /**
     * @var string|callable|null the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given
     * row data. For more details, see [[indexBy()]]. This property is only used by [[QueryInterface::all()|all()]].
     */
    public $indexBy;

    /**
     * @var bool Whether to use the highlighting component.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hl = false;

    /**
     * @var string The highlighting implementation/engine to use. Acceptable values are: unified, original, fastVector.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlMethod = 'unified';

    /**
     * @var string Fields to generate highlighted snippets for. Separate multiple fields with commas.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlFl = null;

    /**
     * @var string Overrides the q parameter for highlighting
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlQ = null;

    /**
     * @var string The query parser to use if the query option is set
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlQParser = null;

    /**
     * @var bool If false, all query terms will be highlighted for each field to be highlighted (hl.fl) no matter what
     * fields the parsed query refer to. If set to true, only query terms aligning with the field being highlighted
     * will in turn be highlighted.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlRequireFieldMatch = false;

    /**
     * @var bool If set to true, Solr will highlight phrase queries (and other advanced position-sensitive queries) accurately
     * as phrases. If false, the parts of the phrase will be highlighted everywhere instead of only when it forms the given phrase.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlUsePhraseHighlighter = false;

    /**
     * @var int Maximum number of snippets per field
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlSnippets = 1;

    /**
     * @var int The size, in characters, of fragments to consider for highlighting
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlFragsize = 100;

    /**
     * @var string Solr option hl.tag.pre
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlTagPre = '<em>';

    /**
     * @var string Solr option hl.tag.post
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlTagPost = '</em>';

    /**
     * @var string If blank, then the stored text will be returned without any escaping/encoding
     * performed by the highlighter. If set to html then special HTML/XML characters will be encoded
     * (e.g., & becomes &amp;). The pre- and post-snippet characters are never encoded.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public $hlEncoder = null;

    /**
     * @var string|array Boost Query Parameter
     * @see https://solr.apache.org/guide/solr/latest/query-guide/dismax-query-parser.html#bq-boost-query-parameter
     */
    public $bq = [];

    /**
     * @var string Boost Functions Parameter
     * @see https://solr.apache.org/guide/solr/latest/query-guide/dismax-query-parser.html#bf-boost-functions-parameter
     */
    public $bf = null;

    /**
     * @var string Boost Functions Parameter for the EdisMax Query Handler. It behaves differently than bf!
     * @see https://solr.apache.org/guide/solr/latest/query-guide/edismax-query-parser.html
     *
     * Also
     * @see https://solr.apache.org/guide/solr/latest/query-guide/dismax-query-parser.html#bq-bf-shortcomings
     */
    public $boost = null;


    /**
     * @var string Query Fields Parameter
     * @see https://solr.apache.org/guide/solr/latest/query-guide/dismax-query-parser.html#qf-query-fields-parameter
     */
    public $qf = null;

    /**
     * Whether to use the EDisMax Query Handler
     * @var bool using the EDisMax Handler
     */
    public $edismax = false;

    /**
     * Whether to use spellchecking
     * @see https://solr.apache.org/guide/solr/latest/query-guide/spell-checking.html
     * @var bool using spellcheck component
     */
    public $spellcheck = false;

    public $spellcheckQuery = null;

    public $spellcheckBuild = false;

    public $spellcheckCount = 5;

    public $spellcheckCollate = false;

    public $spellcheckOnlyMorePopular = false;

    public $spellcheckExtendedResults = false;

    public $spellcheckCollateExtendedResults = false;

    /**
     * Grouping
     * @see https://solr.apache.org/guide/8_0/result-grouping.html
     * @see https://solarium.readthedocs.io/en/stable/queries/select-query/building-a-select-query/components/grouping-component/
     */

    public $group = false;
    # Array of all group-queries added was by ->groupAddQuery()
    public $groupQueries = [];

    # Array of all group-queries added was by ->groupAddField()
    public $groupFields = [];

    public $groupLimit = null;

    public $groupOffset = null;

    public $groupSort = null;

    public $groupNumberOfGroups = null;

    public $groupCachepercentage = null;

    public $groupFormat = null;

    // Grouping facet parameters
    public $groupTruncate = null;
    public $groupFacet = null;

    /**
     * Facets Common Options
     */
    public $facetSetPrefix = null;
    public $facetSetContains = null;
    public $facetSetMatches = null;
    public $facetSetExcludeTerms = null;
    public $facetSetSort = null;
    public $facetSetLimit = null;

    /**
     * Facets Fields
     */
    public $facetCreateFacetField = null;
    public $facetSetField = null;
    public $facetSetTerms = null;

    /**
     * Facets Query
     */
    public $facetCreateFacetQuery = null;
    public $facetSetQuery = null;

    /**
     * Facet MultiQuery
     */
    public $facetCreateFacetMultiQuery = null;
    public $facetCreateQuery = null;

    /**
     * Facet Range
     */
    public $facetCreateFacetRange = null;
    public $facetSetStart = null;
    public $facetSetEnd = null;
    public $facetSetGap = null;

    /**
     * MoreLikeThis
     */
    public $mlt = false;
    public $mltQuery = null ;
    public $mltStart = null ;
    public $mltRows = null ;
    public $mltFields = null ;
    public $mltSort = null ;
    public $mltMatchInclude = null ;
    public $mltInterestingTerms = null ;
    public $mltMltFields = null ;
    public $mltMinimumTermFrequency = null ;
    public $mltMinimumDocumentFrequency = null ;
    public $mltMaximumDocumentFrequency = null ;
    public $mltMaximumDocumentFrequencyPercentage = null ;
    public $mltMinimumWordLength = null ;
    public $mltMaximumWordLength = null ;
    public $mltMaximumQueryTerms = null ;
    public $mltMaximumNumberOfTokens = null ;
    public $mltBoost = null ;
    public $mltQueryFields = null ;

    /**
     * Creates a DB command that can be used to execute this query.
     * @param Connection $db the database connection used to execute the query.
     * If this parameter is not given, the `solr` application
     * component will be used.
     * @return Command the created DB command instance.
     */
    public function createCommand($db = null)
    {
        if ($db === null) {
            $db = Yii::$app->get('solr');
        }

        $commandConfig = $db->getQueryBuilder()->build($this);

        return $db->createCommand(null, $commandConfig);
    }

    /**
     * Enables query cache for this Query.
     * @param int|true $duration the number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * Use a negative number to indicate that query cache should not be used.
     * @param \yii\caching\Dependency|null $dependency the cache dependency associated with the cached result.
     * @return $this the Query object itself
     * @since 2.0.14
     */
    public function cache($duration = 0, $dependency = null)
    {
        $this->queryCacheDuration = $duration;
        $this->queryCacheDependency = $dependency;
        return $this;
    }

    /**
     * Disables query cache for this Query.
     * @return $this the Query object itself
     * @since 2.0.14
     */
    public function noCache()
    {
        $this->queryCacheDuration = -1;
        return $this;
    }

    /**
     * @var string setting the search phrase
     */
	public function q($q)
	{
	    $this->q = $q;
	    return $this;
	}

    /**
     * Sets the Field List Parameter of a SOLR search.
     * @var string|array names of fields to fetch e.g ` ['title', 'text']` or
     * `'title, text'`
     */
    public function select($fields)
    {
        $this->select = $fields;
    	return $this;
    }

	public function fl($fields)
	{
	    return $this->select($fields);
	}

	public function offset($offset)
	{
	    $this->offset = $offset;
	    return $this;
	}

	public function start($offset)
	{
	    return $this->offset($offset);
	}

	public function limit($limit)
	{
	    $this->limit = $limit;
	    return $this;
	}

	public function rows($limit)
	{
	    return $this->limit($limit);
	}

    /**
     * @var array|string field name to sort plus order, e.g.
     * ['date' => 'desc'] or 'date, desc'.
     * Multiple fields may be provided, e.g. `['date' => 'asc', 'price' => 'desc']`
     * Valid order types are `asc`, `ASC`, `DESC`, `desc`
     */
    public function orderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

	public function sort($orderBy)
	{
	    return $this->orderBy($orderBy);
	}

    /**
     * condition is provided.
     *
     * A search can be filtered with multiple Filterqueries (aka &fq= parameter).
     * So chaing multiple where() leads to multiple fq= parameter.
     *
     * Examples:
     *
     * `where(['price' => '[1 TO 300]'])`
     * `where(['animal' => 'dog', 'sound' => 'barks'])`
     *
     * The following will split up to a OR search:
     * `animal: (dog OR cat)`
     *
     * `where(['animal' => ['dog', 'cat']])
     *
     * This one will split up as follows
     * `animal: (dog OR cat) AND sound: meow`
     *
     * ```php
     * where([
     *  'animal' => ['dog', 'cat'],
     *  'sound' => 'meow'
     * ], 'and')
     * ```
     *
     * This one goes to:
     * `animal: dog OR sound: barks`
     *
     * `where(['animal' => 'dog', 'sound' => 'barks'], 'or')`
     *
     * Every where() call adds an extra fq= call so SOLR.
     *
     * @param array condition of WHERE statemend (aka Filter Query) like `[$field => $query]`
     * @param string search operator that is used to chain $conditions in case more than 1
     * @see fq()
     *
     * @return Query
     *
     */
    public function where(array $condition = null, $operator = 'and')
    {
        if ($condition !== null) {
            $this->where[] = [
                'condition' => $condition,
                'operator' => $operator
            ];
        }

        return $this;
    }

	public function fq(array $condition = null, $operator = 'and')
	{
	    return $this->where($condition, $operator);
	}

    /**
     * Shortcut for `where($condition, 'and')`
     *
     * @var array build an AND Filterquery. e.g.
     * foo: bar AND faz: baz
     * @return Query
     */
    public function andWhere(array $condition = null)
    {
        if ($condition !== null) {
            $this->where($condition, 'and');
        }

        return $this;
    }

    /**
     * Shortcut for `where($condition, 'or')`
     *
     * @var array build an OR Filterquery. e.g.
     * foo: bar OR faz: baz
     * @return Query
     */
    public function orWhere(array $condition = null)
    {
        if ($condition !== null) {
            $this->where($condition, 'or');
        }
        return $this;
    }

    /**
     * Because EDisMax supports all DisMax query parameter, we just use
     * the EdisMax Handler. This is for convinience.
     * Activating this affects the handling of the query string.
     * @return this
     */
    public function dismax()
    {
        $this->edismax = true;
        return $this;
    }

    /**
     * Whether to use the EDisMax Query Handler
     * Activating this affects the handling of the query string.
     * @return this
     */
    public function edismax()
    {
        $this->edismax = true;
        return $this;
    }

    /**
     * This can be given as string like:
     *
     * `bq('date:[NOW/DAY-1YEAR TO NOW/DAY]')`
     *
     * or as array
     *
     * `bq(['date' => '[NOW/DAY-1YEAR TO NOW/DAY]'])`
     *
     * Multiple calls for multiple `&bq=` are possible:
     *
     * ```php
     *
     * $query->find()
     *       ->bq(['vendor'=> 'foo^2'])
     *       ->bq(['vendor'=> 'bar^1'])
     *
     * ```
     *
     *
     * @var string|array Boost Query
     * @return this
     */
    public function bq($boostQuery)
    {
        if (is_array($boostQuery)) {
            $this->bq[] = $boostQuery;
        }

        if (is_string($boostQuery)) {
            $this->bq = $boostQuery;
        }

        return $this->edismax();
    }

    /**
     * @var string Boost Function
     * @return this
     */
    public function bf($boostFunction)
    {
        $this->bf = $boostFunction;
        return $this->edismax();
    }

    /**
     * @var string Boost Function in EdixMax Context
     * @return this
     */
    public function boost($boostFunction)
    {
        $this->boost = $boostFunction;
        return $this->edismax();
    }

    /**
     * @var string Query Fields
     * @return this
     */
    public function qf($queryFields)
    {
        $this->qf = $queryFields;
        return $this->edismax();
    }




    /**
     * Sets a highlight parameters to retrieve from the documents.
     * @param array $highlight array of parameters to highlight results.
     * @return $this the query object itself
     */
    public function hl()
    {
        $this->hl = true;
        return $this;
    }

    public function hlMethod(string $method)
    {
        $this->hlMethod = $method;
        return $this->hl();
    }

    /**
     * @var string Fields to generate highlighted snippets for. Separate multiple fields with commas.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlFl(string $fields)
    {
        $this->hlFl = $fields;
        return $this->hl();
    }

    /**
     * @var string Overrides the q parameter for highlighting
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlQ(string $queryString)
    {
        $this->hlQ = $queryString;
        return $this->hl();
    }

    /**
     * @var string The query parser to use if the query option is set
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlQParser(string $queryParser)
    {
        $this->hlQParser = $queryParser;
        return $this->hl();
    }

    /**
     * @var bool If false, all query terms will be highlighted for each field to be highlighted (hl.fl) no matter what
     * fields the parsed query refer to. If set to true, only query terms aligning with the field being highlighted
     * will in turn be highlighted.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlRequireFieldMatch(bool $requireFM)
    {
        $this->hlRequireFieldMatch = $requireFM;
        return $this->hl();
    }

    /**
     * @var bool If set to true, Solr will highlight phrase queries (and other advanced position-sensitive queries) accurately
     * as phrases. If false, the parts of the phrase will be highlighted everywhere instead of only when it forms the given phrase.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlUsePhraseHighlighter(bool $usePhraseHighlighter)
    {
        $this->hlUsePhraseHighlighter = $usePhraseHighlighter;
        return $this->hl();
    }

    /**
     * @var int Maximum number of snippets per field
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlSnippets(int $snippets)
    {
        $this->hlSnippets = $snippets;
        return $this->hl();
    }

    /**
     * @var int The size, in characters, of fragments to consider for highlighting
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlFragsize(int $fragsize)
    {
        $this->hlFragsize = $fragsize;
        return $this->hl();
    }

    /**
     * @var string Solr option hl.tag.pre
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlTagPre(string $tagPre)
    {
        $this->hlTagPre = $tagPre;
        return $this->hl();
    }

    /**
     * @var string Solr option hl.tag.post
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlTagPost(string $tagPost)
    {
        $this->hlTagPost = $tagPost;
        return $this->hl();
    }

    /**
     * @var string If blank, then the stored text will be returned without any escaping/encoding
     * performed by the highlighter. If set to html then special HTML/XML characters will be encoded
     * (e.g., & becomes &amp;). The pre- and post-snippet characters are never encoded.
     * @see https://solr.apache.org/guide/solr/latest/query-guide/highlighting.html
     */
    public function hlEncoder(string $encoder)
    {
        $this->hlEncoder = $encoder;
        return $this->hl();
    }

    /**
     * Whether to activate spellchecking
     * @see https://solr.apache.org/guide/solr/latest/query-guide/spell-checking.html
     */
    public function spellcheck()
    {
        $this->spellcheck = true;
        return $this;
    }

    public function spellcheckQuery(string $query) {
        $this->spellcheckQuery = $query;
        return $this->spellcheck();
    }

    public function spellcheckBuild() {
        $this->spellcheckBuild = true;
        return $this->spellcheck();
    }

    public function spellcheckCount(int $count) {
        $this->spellcheckCount = $count;
        return $this->spellcheck();
    }

    public function spellcheckCollate() {
        $this->spellcheckCollate = true;
        return $this->spellcheck();
    }

    public function spellcheckOnlyMorePopular() {
        $this->spellcheckOnlyMorePopular = true;
        return $this->spellcheck();
    }

    public function spellcheckExtendedResults() {
        $this->spellcheckExtendedResults = true;
        return $this->spellcheck();
    }

    public function spellcheckCollateExtendedResults() {
        $this->spellcheckCollateExtendedResults = true;
        return $this->spellcheck();
    }

    /**
     * enable grouping by setting ...find()->group()->...
     * set rows `find()->rows(..)` to define the number of groups
     * that should be returned (relevant for grouping per field)
     *
     * @return void
     *
     */
    public function group() 
    {
        $this->group = true;
        return $this;
    }
    /**
     * adding groupQuery with
     * find()->group()->groupAddQuery('foo')->groupAddQuery(...)
     * Multiple addQuery results in multiple groups, 
     * ignoring the numbers of rows (which is in fact "number of groups").
     * 
     * @param string $term
     * 
     */
    public function groupAddQuery(string $term) 
    {
        if (gettype($term) == 'string') {
            $this->groupQueries[] = $term;
        }
        
        return $this;
    }

    /**
     * Adding Field to group by.
     * adding groupField with:
     * find()->group()->groupAddField('foo')->groupAddField(...)
     * 
     * @param string $term
     * 
     */
    public function groupAddField(string $term) 
    {
        if (gettype($term) == 'string') {
            $this->groupFields[] = $term;
        }
        
        return $this;
    }

    /**
     * The number of documents per group, if groupSetFormat('grouped')
     * Has no effect, for groupSetFormat('simple'). Use ->limit() instead.
     * ...for any reason.
     * 
     * This will not set the numer of groups.
     * The Numer of groups is defined by the
     * row parameter (for grouping by field)
     * or the numer of queries added by addQuery().
     *
     * @param integer $limit
     *
     * @return void
     *
     */
    public function groupSetLimit(int $limit)
    {
        if (gettype($limit) == 'integer') {
            $this->groupLimit = $limit;
        }
        
        return $this;
    }

    /**
     * The offset of the document-block per group.
     * This ist the group.start parameter from solr,
     * that is called "offset" in solarium
     * for any reason.
     *
     * @param integer $offset
     *
     * @return void
     *
     */
    public function groupSetOffset(int $offset)
    {
        if (gettype($offset) == 'integer') {
            $this->groupOffset = $offset;
        }
        
        return $this;
    }


    /**
     * How to sort documents within a single group.
     *
     * @param string $sort
     *
     * @return void
     *
     */
    public function groupSetSort(string $sort)
    {
        if (gettype($sort) == 'string') {
            $this->groupSort = $sort;
        }
        
        return $this;
    }

    /**
     * Return the number of groups in result-response.
     *
     * @param boolean $sort
     *
     * @return void
     *
     */
    public function groupSetNumberOfGroups(bool $numberOfGroupsEnabled)
    {
        if (gettype($numberOfGroupsEnabled) == 'boolean') {
            $this->groupNumberOfGroups = $numberOfGroupsEnabled;
        }
        
        return $this;
    }
    
    /**
     * Setting Solrs group.cache.percentage
     * Testing has shown that group caching only improves
     * search time with Boolean, wildcard, and fuzzy queries. 
     * For simple queries like term or "match all" queries,
     * group caching degrades performance.
     *
     * @param integer $cachepercentage
     *
     * @return void
     */
    public function groupSetCachepercentage(int $cachepercentage)
    {
        if (gettype($cachepercentage) == 'integer') {
            $this->groupCachepercentage = $cachepercentage;
        }
        
        return $this;
    }


    /**
     * Setting Group-Facet truncate option
     *
     * @param boolean $truncateEnabled
     *
     * @return void
     *
     */
    public function groupSetTruncate(bool $truncateEnabled)
    {
        if (gettype($truncateEnabled) == 'boolean') {
            $this->groupTruncate = $truncateEnabled;
        }
        
        return $this;
    }

    /**
     * Setting the group format. Valide values:
     * - 'simple' use ->limit() to limit documents for each group
     * - 'grouped' use ->groupSetLimit() to limit documents for each group
     *
     * @param string $format
     *
     * @return void
     */
    public function groupSetFormat(string $format)
    {
        if (gettype($format) == 'string') {
            $this->groupFormat = $format;
        }
        
        return $this;
    }

    


    /**
     * Enable facetting for grouping
     *
     * @param boolean $facetEnabled
     *
     * @return void
     */
    public function groupSetFacet(bool $facetEnabled)
    {
        if (gettype($facetEnabled) == 'boolean') {
            $this->groupFacet = $facetEnabled;
        }
        
        return $this;
    }
    
    

    /**
     * Facets Common Options
     */

    public function facetSetPrefix($prefix)
    {
        $this->facetSetPrefix = $prefix;
        return $this;
    }

    public function facetSetContains($contains)
    {
        $this->facetSetContains = $contains;
        return $this;
    }

    public function facetSetMatches($matches)
    {
        $this->facetSetMatches = $matches;
        return $this;
    }

    public function facetSetExcludeTerms($excludeTerms)
    {
        $this->facetSetExcludeTerms = $excludeTerms;
        return $this;
    }

    public function facetSetSort($sort)
    {
        $this->facetSetSort = $sort;
        return $this;
    }

    public function facetSetLimit($limit)
    {
        $this->facetSetLimit = $limit;
        return $this;
    }

    /**
     * Facet Fields
     */
    public function facetCreateFacetField(string $name='facet') {
        $this->facetCreateFacetField = $name;
        return $this;
    }

    public function facetSetField($field)
    {
        $this->facetSetField = $field;
        return $this;
    }

    public function facetSetTerms($terms)
    {
        $this->facetSetTerms = $terms;
        return $this;
    }

    /**
     * Facets Query
     */
    public function facetCreateFacetQuery($name)
    {
        $this->facetCreateFacetQuery = $name;
        return $this;
    }

    public function facetSetQuery($query)
    {
        $this->facetSetQuery = $query;
        return $this;
    }

    /**
     * Facet MultiQuery
     */
    public function facetCreateFacetMultiQuery($name)
    {
        $this->facetCreateFacetMultiQuery = $name;
        return $this;
    }

    public function facetCreateQuery(string $key, string $query)
    {
        $this->facetCreateQuery = [$key, $query];
        return $this;
    }

    /**
     * Facet Range
     */
    public function facetCreateFacetRange(string $range) {
        $this->facetCreateFacetRange = $range;
        return $this;
    }

    public function facetSetStart($start)
    {
        $this->facetSetStart = $start;
        return $this;
    }

    public function facetSetEnd($end)
    {
        $this->facetSetEnd = $end;
        return $this;
    }

    public function facetSetGap($gap)
    {
        $this->facetSetGap = $gap;
        return $this;
    }

    /**
     * Whether to use MoreLikeThis Query Handler
     * This leads in using "/mlt" instead of "/select"
     *
     * @return this
     */
    public function mlt()
    {
        $this->mlt = true;
        return $this;
    }

    /**
     * Query to execute
     *
     * @return this
     */
    public function mltQuery(string $query)
    {
        $this->mltQuery = $query;
        return $this->mlt();
    }

    /**
     * Start position (offset) in the complete Solr query resultset, to paginate big resultsets.
     *
     * @return this
     */
    public function mltStart(int $start = 0)
    {
        $this->mltStart = $start;
        return $this->mlt();
    }

    /**
     * Number of rows to fetch, starting from the 'start' (offset) position.
     * It's a limit, you might get less.
     * @return this
     */
    public function mltRows(int $rows = 10)
    {
        $this->mltRows = $rows;
        return $this->mlt();
    }

    /**
     * Comma separated list of fields to fetch from Solr.
     * There are two special values: '*' meaning 'all fields' and 'score'
     * to also fetch the Solr document score value
     * @return this
     */
    public function mltFields(string $fields)
    {
        $this->mltFields = $fields;
        return $this->mlt();
    }

    /**
     * Array with sort field as key and sort order as values.
     * Multiple entries possible, they are used in the order of the array.
     * Example: array('price' => 'asc')
     * @return this
     */
    public function mltSort(array $sort = [])
    {
        $this->mltSort = $sort;
        return $this->mlt();
    }

    /**
     * Specifies whether or not the response should include the matched document.
     * If set to false, the response will look like a normal select response.
     * @return this
     */
    public function mltMatchInclude(bool $matchinclude = false)
    {
        $this->mltMatchInclude = $matchinclude;
        return $this->mlt();
    }

    /**
     * Controls how the handler presents the "interesting" terms.
     * Must be one of: none, list, details.
     * @return this
     */
    public function mltInterestingTerms(string $terms = 'none')
    {
        $this->mltInterestingTerms = $terms;
        return $this->mlt();
    }

    /**
     * The fields to use for similarity. NOTE: if possible, these should have a
     * stored TermVector. Separate multiple fields with commas.
     * @return this
     */
    public function mltMltFields(string $fields = 'none')
    {
        $this->mltMltFields = $fields;
        return $this->mlt();
    }

    /**
     * Minimum Term Frequency - the frequency below which terms will be ignored in the source doc.
     * @return this
     */
    public function mltMinimumTermFrequency(int $frequency = null)
    {
        $this->mltMinimumTermFrequency = $frequency;
        return $this->mlt();
    }

    /**
     * Minimum Document Frequency - the frequency at which words will be
     * ignored which do not occur in at least this many docs.
     * @return this
     */
    public function mltMinimumDocumentFrequency(int $frequency = null)
    {
        $this->mltMinimumDocumentFrequency = $frequency;
        return $this->mlt();
    }

    /**
     * Maximum Document Frequency - the frequency at which words will be
     * ignored which occur in more than this many docs.
     * @return this
     */
    public function mltMaximumDocumentFrequency(int $frequency = null)
    {
        $this->mltMaximumDocumentFrequency = $frequency;
        return $this->mlt();
    }

    /**
     * Maximum Document Frequency Percentage - a relative ratio at which words
     * will be ignored which occur in more than this percentage of the docs in the index.
     * @return this
     */
    public function mltMaximumDocumentFrequencyPercentage(int $percentage = null)
    {
        $this->mltMaximumDocumentFrequencyPercentage = $percentage;
        return $this->mlt();
    }

    /**
     * Minimum word length below which words will be ignored.
     * @return this
     */
    public function mltMinimumWordLength(int $length = null)
    {
        $this->mltMinimumWordLength = $length;
        return $this->mlt();
    }

    /**
     * Maximum word length below which words will be ignored.
     * @return this
     */
    public function mltMaximumWordLength(int $length = null)
    {
        $this->mltMaximumWordLength = $length;
        return $this->mlt();
    }

    /**
     * Maximum number of query terms that will be included in any generated query.
     * @return this
     */
    public function mltMaximumQueryTerms(int $terms = null)
    {
        $this->mltMaximumQueryTerms = $terms;
        return $this->mlt();
    }

    /**
     * Maximum number of tokens to parse in each example doc field that is not
     * stored with TermVector support.
     * @return this
     */
    public function mltMaximumNumberOfTokens(int $tokens = null)
    {
        $this->mltMaximumNumberOfTokens = $tokens;
        return $this->mlt();
    }

    /**
     * If true the query will be boosted by the interesting term relevance.
     * @return this
     */
    public function mltBoost(bool $boost = false)
    {
        $this->mltBoost = $boost;
        return $this->mlt();
    }

    /**
     * Query fields and their boosts using the same format as that used in
     * DisMaxQParserPlugin. These fields must also be specified in mltfields.
     * Separate multiple fields with commas.
     * @return this
     */
    public function mltQueryFields(string $fields)
    {
        $this->mltQueryFields = $fields;
        return $this->mlt();
    }

    /**
     * Returns the number of records.
     * @return int number of records.
     */
    public function count($db = null)
    {
        return $this->createCommand($db)->queryOne()->getNumFound();
    }

    /**
     * Get highlights out of response
     * @var Response Object
     * @return array highlights
     */
    public function extractHighlights($row) : array
    {
        $highlights = [];

        if (!$row->getHighlighting()) {
            return $highlights;
        }

        foreach ($row->getHighlighting() as $highlight)
        {
            $highlights[] = $highlight->getFields();
        }

        return $highlights;
    }

    /**
     * Get spellcheck out of response
     * @var Response Object
     * @return SpellCheck/Result Object
     */
    public function extractSpellchecks($result)
    {
        $spellcheck = null;

        if (!$result->getSpellcheck()) {
            return $spellcheck;
        }

        return $result->getSpellcheck();
    }
/*
    public function _extractGroups($result)
    {
        $group = null;
        if (!$result->getGrouping()) {
            return $group;
        }
        return $result->getGrouping();
    }*/

    /**
     * Get facets out of response
     * @var Response Object
     * @return Facet Object
     */
    public function extractFacets($result)
    {
        $facets = null;

        if (!$result->getFacetSet()) {
            return $facets;
        }

        return $result->getFacetSet();
    }

    /**
     * Executes the query and returns a single row of result.
     * @param Connection|null $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return Result the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     */
	public function one($db = null)
	{
	    $row = $this->createCommand($db)->queryOne();

        $doc = [];

        // facets
        $facets = $this->extractFacets($row);

        if ($facets) {
            $doc['_facets'] = $facets;
        }

        // Spellchecking
        $spellcheck = $this->extractSpellchecks($row);
        if ($spellcheck) {
            $doc['_spellcheck'] = $spellcheck;
        }

        if ($row->getNumFound() == 0) {
            // Even if nothing was found, maybe there are helpfull suggestions :-)
            if ($spellcheck) {
                return $doc;
            }
            return false;
        }

        // Are any highlights present?
        $highlights = $this->extractHighlights($row);

        foreach ($row as $index => $document) {
            // this works, because the highlights in the HL object are in the same order, as the
            // documents.
            ($highlights) ? $doc['_highlights'] = $highlights[$index] : $doc['_highlights'] = null;

            foreach ($document as $field => $value) {
                $doc[$field] = $value;
            }
        }

        return $doc;
	}

    /**
     * Running a grouped query on Solr and return the result as grouping object.
     *
     * @param Connection|null $db the database connection used to generate the SQL statement.
     *
     * @return null|Solarium\Component\Result\Grouping\Result object
     * 
     */
    public function grouping($db = null)
    {
        $result = $this->createCommand($db)->queryAll();

        return $result->getGrouping();
        

    }

    /**
     * Executes the query and returns all results as an array.
     * @param Connection|null $db the database connection used to generate the SQL statement.
     * If this parameter is not given, the `db` application component will be used.
     * @return array the query results. If the query results in nothing, an empty array will be returned.
     */
	public function all($db = null)
	{
	    $rows = $this->createCommand($db)->queryAll(); 

        return $this->populate($rows);
	}

    /**
     * Converts the raw query results into the format as specified by this
     * query. This method is internally used to convert the data fetched from
     * database into the format as required by this query.
     * @param array $rows the raw query result from database
     * @return array the converted query result
     */
    public function populate($rows)
    {
        $docs = [];
        $arrayIndex = 0;
        $indexBy = null;

        if ($this->indexBy !== null) {
            $indexBy = $this->indexBy;
        }

        // facets
        $facets = $this->extractFacets($rows);

        if ($facets) {
            $docs[]['_facets'] = $facets;
        }

        // Spellchecking
        $spellcheck = $this->extractSpellchecks($rows);

        if ($rows->getNumFound() == 0) {
            if ($spellcheck) {
                $docs[]['_spellcheck'] = $spellcheck;
            } else {
                return [];
            }
        }
        

        // Are any highlights present?
        $highlights = $this->extractHighlights($rows);

        foreach ($rows as $index => $document)
        {
            if ($indexBy) {
                $arrayIndex = $document->$indexBy;
                !is_array($arrayIndex) ? : $arrayIndex = implode('_', $arrayIndex);
            }

            foreach ($document as $field => $value) {
                ($highlights) ? $docs[$arrayIndex]['_highlights'] = $highlights[$index] : $docs[$arrayIndex]['_highlights'] = null;
                ($spellcheck) ? $docs[$arrayIndex]['_spellcheck'] = $spellcheck : $docs[$arrayIndex]['_spellcheck'] = null;
                $docs[$arrayIndex][$field] = $value;
            }

            if (is_int($arrayIndex)) {
                $arrayIndex++;
            }
        }

        return $docs;
    }

    /**
     * Sets the [[indexBy]] property.
     * @param string $column the name of the column by which the query results should be indexed by.
     * @return $this the query object itself
     */
    public function indexBy($column)
    {
        $this->indexBy = $column;
        return $this;
    }

}
