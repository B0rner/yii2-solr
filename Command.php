<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace b0rner\solr;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidArgumentException;

/**
 * The Command class implements the API for accessing the Solarium Library.
 *
 */
class Command extends Component
{

    /**
     * @var Connection
     */
    public $db;

    /**
     * @var string Search Phrase if given via createCommand
     */
    public $phrase;

    /**
     * @var Solarium Client
     */
    public $client;

    /**
     * @var array list of arrays that become parts of a query
     */
    public $queryParts;

    /**
     * @var int the default number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire. And use a negative number to indicate
     * query cache should not be used.
     * @see cache()
     */
    public $queryCacheDuration = -1;
    /**
     * @var \yii\caching\Dependency the dependency to be associated with the cached query result for this command
     * @see cache()
     */
    public $queryCacheDependency;

 	/**
 	 * @var Solarium Select Query Instance
 	 */
 	public $query;

    /**
     * @var Solarium Update Query Instance
     */
    public $update;

    /**
     * @var whether to use the MoreLikeThis Handler
     */
    public $mlt;

 	/**
 	 * @var eDisMax instance
 	 */
 	public $edismax = null;

    /**
     * @var highlighting component
     */
    public $hl = null;

    /**
     * @var spellchecking component
     */
    public $spellcheck = null;

    /**
     * @var facetting component
     */
    public $facetSet = null;


    /**
     * @var grouping component
     */
    public $grouping = null;

 	/**
 	 * Instanciate a query Instance
 	 */
 	public function init()
 	{
 		if ($this->db instanceof Connection) {
            $this->client = $this->db->client;
            $this->update = $this->client->createUpdate();
 		}

        // What Query Handler to use?
        // In case of MoreLikeThis, we need to switch to the "/mlt" Query Handler
        if (is_array($this->queryParts) && array_key_exists('mlt', $this->queryParts) &&
            $this->queryParts['mlt']) {
            $this->query = $this->client->createMoreLikeThis();
            $this->mlt = true;
        } else {
            $this->query = $this->client->createSelect();
        }

        if (is_string($this->phrase)) {
            $this->queryParts['q'] = $this->phrase;
        }

        // activating the EDisMax Handler
        if (is_array($this->queryParts) && array_key_exists('edismax', $this->queryParts) &&
            $this->queryParts['edismax']) {
            $this->edismax = $this->query->getEDisMax();
        }

        // highlighting
        if (is_array($this->queryParts) && array_key_exists('hl', $this->queryParts) &&
            $this->queryParts['hl']) {
            $this->hl = $this->query->getHighlighting();
        }

        // spellchecking
        if (is_array($this->queryParts) && array_key_exists('spellcheck', $this->queryParts) &&
            $this->queryParts['spellcheck']) {
            $this->spellcheck = $this->query->getSpellcheck();
        }

        // Facets
        if (is_array($this->queryParts)) {
            $this->facetSet = $this->query->getFacetSet();
        }

         // Caching
        if (is_array($this->queryParts) && array_key_exists('queryCacheDuration', $this->queryParts) &&
            $this->queryParts['queryCacheDuration']) {
            $this->queryCacheDuration = $this->queryParts['queryCacheDuration'];
        }

        if (is_array($this->queryParts) && array_key_exists('queryCacheDependency', $this->queryParts) &&
            $this->queryParts['queryCacheDependency']) {
            $this->queryCacheDependency = $this->queryParts['queryCacheDependency'];
        }

        // Grouping wenn in Client.php $this->group = True ist, hat der Nutzer Grouing aktiviert.
        if (is_array($this->queryParts) && array_key_exists('group', $this->queryParts) &&  #key group, wenn wir ->group() nutzen
            $this->queryParts['group']) {
                // grouping enabled
                $this->grouping = $this->query->getGrouping(); # Solarium Grouping-Componente für das Bauen der Query, setting &group=true as solr param
        }


 		parent::init();
 	}

    /**
     * Get complete Index Schema with a LUKE Query.
     * @return array all fields with their corresponding FieldObjects
     */
    public function getIndexSchema() {
        $lukeQuery = $this->client->createLuke();
        $lukeQuery->setShow($lukeQuery::SHOW_ALL);

        // omitting index flags for each field can speed up Luke requests
        //$lukeQuery->setIncludeIndexFieldFlags(false);

        $result = $this->client->luke($lukeQuery);

        return $result->getFields();
    }

    /**
     * @return array with all known fields.
     */
    public function getIndexFields() {
        return array_keys($this->getIndexSchema());
    }

    /**
     * This inserts a document.
     * As long as the corresponding uniqueId is not set, SOLR will
     * create a uniqueId by itself.
     * If a uniqueId is provided and a document with that uniqueId is already
     * present in the index, this document will be updated.
     *
     * So insert can also be used as an update() function as long as an existing
     * uniqueId is provided.
     *
     * @var attributes dirty attributes,
     * @var uniqueId name of the uniqueId field
     * @return ?
     */
    public function insert(array $attributes)
    {

        // this is needed in case one declares properties in the Model that are
        // not present in den SOLR schema.
        // We have to remove those fieldnames that are unkown to the index.
        if (!empty($this->getIndexSchema())) {
            $attributes = array_intersect_key($attributes, $this->getIndexSchema());
        }

        $document = $this->update->createDocument();
        foreach ($attributes as $key => $value) {
            $document->$key = $value;
        }

        $this->update->addDocument($document);
        return $this->execute();
    }

    /**
     * Execute insert, update, delete
     * The commit will be done just before executing.
     * @return Result Object
     */
    public function execute()
    {
        $this->update->addCommit();
        return $this->client->update($this->update);
    }

    /**
     * Delete one or more documents
     * A fieldname and a value for that field has ro be provided
     *
     * For example
     *
     * `$db->createCommand()->delete('color', 'red');`
     *
     * deletes all documents, where the 'color' field contains
     * 'red'
     *
     * @var string field name
     * @var string value of field
     *
     */
    public function delete($field, $value)
    {
        $this->update->addDeleteQuery($field . ':' . $value);
        return $this->execute();
    }


    /**
     * Query for $limit number of rows
     */
 	public function queryAll()
 	{
        if (!empty($this->queryParts['limit']) || $this->queryParts['limit'] > 0) {
            $this->query->setRows($this->queryParts['limit']);
        }

 		return $this->queryInternal();
 	}

    /**
     * Query for 1 rows and ignore $limit
     */
 	public function queryOne()
 	{
        $this->queryParts['limit'] = 1;
 		$this->query->setRows(1);
 		return $this->queryInternal();
 	}

    /**
     * Finally build a Solarium Query Object with all provided query parts
     * and fire it against the /select Query handler.
     * @return Result Searchreasults
     */
 	protected function queryInternal()
 	{
        // if $this->phrase is set, a search phrase ist directly given to
        // the createCommand function of the connections.
        // In this case we emulate the behavior as with standard SQL createConnections.
        if ($this->phrase) {
            return $this->db->client->select($this->query);
        }

        // the search phrase
        $this->query->setQuery($this->queryParts['q']);

        // fields to show, aka &fl=
        $this->query->setFields($this->queryParts['select']);

        // documents to skip, aka offset, aka start=
        $this->query->setStart($this->queryParts['offset']);

        // sorting aka orderBy
        foreach ($this->queryParts['orderBy'] as $field => $order)
        {
            $this->query->addSort($field, $order);
        }

        // filter queries, aka fq=
        if (!empty($this->queryParts['where'])) {
            foreach ($this->queryParts['where'] as $fq) {
                $this->query->createFilterQuery($fq)->setQuery($fq);
            }
        }
        // DisMax and EDisMax functions
        if (!empty($this->queryParts['bq'])) {
            foreach ($this->queryParts['bq'] as $boost) {
                $this->edismax->setBoostQuery($boost);
            }
        }

        if ($this->queryParts['bf']) {
            $this->edismax->setBoostFunctions($this->queryParts['bf']);
        }

        if ($this->queryParts['boost']) {
            $this->edismax->setBoostFunctionsMult($this->queryParts['boost']);
        }

        // qf (Query Fields) parameter for EDisMax
        if (!empty($this->queryParts['qf'])) {
            $this->edismax->setQueryFields($this->queryParts['qf']);
        }

        // Highlighting
        if ($this->hl) {

            !($this->queryParts['hlQ']) ? : $this->hl->setQuery($this->queryParts['hlQ']);

            !($this->queryParts['hlQParser']) ? : $this->hl->setQueryParser($this->queryParts['hlQParser']);

            $this->hl->setMethod($this->queryParts['hlMethod']);
            $this->hl->setFields($this->queryParts['hlFl']);

            $this->hl->setRequireFieldMatch($this->queryParts['hlRequireFieldMatch']);
            $this->hl->setUsePhraseHighlighter($this->queryParts['hlUsePhraseHighlighter']);
            $this->hl->setSnippets($this->queryParts['hlSnippets']);
            $this->hl->setFragSize($this->queryParts['hlFragsize']);
            $this->hl->setTagPrefix($this->queryParts['hlTagPre']);
            $this->hl->setTagPostfix($this->queryParts['hlTagPost']);
            !($this->queryParts['hlEncoder']) ? :$this->hl->setEncoder($this->queryParts['hlEncoder']);
        }

        // Spellchecking
        if ($this->spellcheck) {

            !($this->queryParts['spellcheckQuery']) ? : $this->spellcheck->setQuery($this->queryParts['spellcheckQuery']);
            $this->spellcheck->setBuild($this->queryParts['spellcheckBuild']);
            $this->spellcheck->setCount($this->queryParts['spellcheckCount']);
            $this->spellcheck->setCollate($this->queryParts['spellcheckCollate']);
            $this->spellcheck->setOnlyMorePopular($this->queryParts['spellcheckOnlyMorePopular']);
            $this->spellcheck->setExtendedResults($this->queryParts['spellcheckExtendedResults']);
            $this->spellcheck->setCollateExtendedResults($this->queryParts['spellcheckCollateExtendedResults']);
        }

        if ($this->grouping) {
            if (count($this->queryParts['groupQueries']) >0)
                array_walk($this->queryParts['groupQueries'], function($query) {
                    $this->grouping->addQuery($query);
                });
            if (count($this->queryParts['groupFields']) >0)
                array_walk($this->queryParts['groupFields'], function($field) {
                    $this->grouping->addField($field);
                });
            if ($this->queryParts['groupLimit']) {
                $this->grouping->setLimit($this->queryParts['groupLimit']);
            }
            if ($this->queryParts['groupOffset']) {
                $this->grouping->setOffset($this->queryParts['groupOffset']);
            }
            if ($this->queryParts['groupSort']) {
                $this->grouping->setSort($this->queryParts['groupSort']);
            }
            if ($this->queryParts['groupNumberOfGroups'] !== null) {
                $this->grouping->setNumberOfGroups($this->queryParts['groupNumberOfGroups'] );
            }
            if ($this->queryParts['groupCachepercentage']) {
                $this->grouping->setCachepercentage($this->queryParts['groupCachepercentage'] );
            }
            if ($this->queryParts['groupTruncate'] !== null) {
                $this->grouping->setTruncate($this->queryParts['groupTruncate'] );
            }
            if ($this->queryParts['groupFacet'] !== null) {
                $this->grouping->setFacet($this->queryParts['groupFacet'] );
            }
            if ($this->queryParts['groupFormat']) {
                $this->grouping->setFormat($this->queryParts['groupFormat'] );
            }
        }

        // Facets
        if ($this->facetSet) {

            // Common Options

            if($this->queryParts['facetSetPrefix']) {
                $this->facetSet->setPrefix($this->queryParts['facetSetPrefix']);
            }

            if($this->queryParts['facetSetContains']) {
                $this->facetSet->setContains($this->queryParts['facetSetContains']);
            }

            if($this->queryParts['facetSetMatches']) {
                $this->facetSet->setMatches($this->queryParts['facetSetMatches']);
            }

            if($this->queryParts['facetSetExcludeTerms']) {
                $this->facetSet->setExcludeTerms($this->queryParts['facetSetExcludeTerms']);
            }

            if($this->queryParts['facetSetSort']) {
                $this->facetSet->setSort($this->queryParts['facetSetSort']);
            }

            if($this->queryParts['facetSetLimit']) {
                $this->facetSet->setLimit($this->queryParts['facetSetLimit']);
            }

            // Facet Field
            if ($this->queryParts['facetCreateFacetField']) {
                $facetField = $this->facetSet->createFacetField($this->queryParts['facetCreateFacetField']);

                if($this->queryParts['facetSetField']) {
                    $facetField->setField($this->queryParts['facetSetField']);
                }

                if($this->queryParts['facetSetTerms']) {
                    $facetField->setTerms($this->queryParts['facetSetTerms']);
                }

            }

            // Facet Query
            if ($this->queryParts['facetCreateFacetQuery']) {
                $facetQuery = $this->facetSet->createFacetQuery($this->queryParts['facetCreateFacetQuery']);

                if($this->queryParts['facetSetQuery']) {
                    $facetQuery->setQuery($this->queryParts['facetSetQuery']);
                }
            }

            // Facet MultiQuery
            if ($this->queryParts['facetCreateFacetMultiQuery']) {
                $facetMultiQuery = $this->facetSet->createFacetMultiQuery($this->queryParts['facetCreateFacetMultiQuery']);

                if($this->queryParts['facetCreateQuery']) {
                    $facetMultiQuery->createQuery(
                        $this->queryParts['facetCreateQuery'][0],
                        $this->queryParts['facetCreateQuery'][1]);
                }
            }

            // Facet Range
            if ($this->queryParts['facetCreateFacetRange']) {
                $facetRange = $this->facetSet->createFacetRange($this->queryParts['facetCreateFacetRange']);

                if($this->queryParts['facetSetField']) {
                    $facetRange->setField($this->queryParts['facetSetField']);
                }
                if($this->queryParts['facetSetStart']) {
                    $facetRange->setStart($this->queryParts['facetSetStart']);
                }
                if($this->queryParts['facetSetEnd']) {
                    $facetRange->setEnd($this->queryParts['facetSetEnd']);
                }
                if($this->queryParts['facetSetGap']) {
                    $facetRange->setGap($this->queryParts['facetSetGap']);
                }
            }


        }

        // MoreLikeThis
        if ($this->mlt) {

            !($this->queryParts['mltQuery']) ? : $this->query->setQuery($this->queryParts['mltQuery']);
            !($this->queryParts['mltStart']) ? : $this->query->setStart($this->queryParts['mltStart']);
            !($this->queryParts['mltRows']) ? : $this->query->setRows($this->queryParts['mltRows']);
            !($this->queryParts['mltFields']) ? : $this->query->setFields($this->queryParts['mltFields']);
            !($this->queryParts['mltSort']) ? : $this->query->setSort($this->queryParts['mltSort']);
            !($this->queryParts['mltMatchInclude']) ? : $this->query->setMatchInclude($this->queryParts['mltMatchInclude']);
            !($this->queryParts['mltInterestingTerms']) ? : $this->query->setInterestingTerms(
                $this->queryParts['mltInterestingTerms']);
            !($this->queryParts['mltMltFields']) ? : $this->query->setMltFields($this->queryParts['mltMltFields']);
            !($this->queryParts['mltMinimumTermFrequency']) ? : $this->query->setMinimumTermFrequency(
                $this->queryParts['mltMinimumTermFrequency']);
            !($this->queryParts['mltMinimumDocumentFrequency']) ? : $this->query->setMinimumDocumentFrequency(
                $this->queryParts['mltMinimumDocumentFrequency']);
            !($this->queryParts['mltMaximumDocumentFrequency']) ? : $this->query->setMaximumDocumentFrequency(
                $this->queryParts['mltMaximumDocumentFrequency']);
            !($this->queryParts['mltMaximumDocumentFrequencyPercentage']) ? : $this->query
                ->setMaximumDocumentFrequencyPercentage($this->queryParts['mltMaximumDocumentFrequencyPercentage']);
            !($this->queryParts['mltMinimumWordLength']) ? : $this->query->setMinimumWordLength(
                $this->queryParts['mltMinimumWordLength']);
            !($this->queryParts['mltMaximumWordLength']) ? : $this->query->setMaximumWordLength(
                $this->queryParts['mltMaximumWordLength']);
            !($this->queryParts['mltMaximumQueryTerms']) ? : $this->query->setMaximumQueryTerms(
                $this->queryParts['mltMaximumQueryTerms']);
            !($this->queryParts['mltMaximumNumberOfTokens']) ? : $this->query->setMaximumNumberOfTokens(
                $this->queryParts['mltMaximumNumberOfTokens']);
            !($this->queryParts['mltBoost']) ? : $this->query->setBoost(
                $this->queryParts['mltBoost']);
            !($this->queryParts['mltQueryFields']) ? : $this->query->setQueryFields(
                $this->queryParts['mltQueryFields']);

        }

        // No caching - default
        if (is_int($this->queryCacheDuration) && $this->queryCacheDuration < 0) {
            return $this->client->select($this->query);
        }

        // Cache queries
        $cacheKey = "Search" . substr(md5(json_encode($this->queryParts)), -8);

        $result = Yii::$app->cache->getOrSet($cacheKey, function () {
            return $this->client->select($this->query);
        }, $this->queryCacheDuration, $this->queryCacheDependency);

        return $result;
 	}

}