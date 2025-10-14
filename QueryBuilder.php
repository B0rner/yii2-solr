<?php

/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 *
 * @author ralf.matulat@bundestag.de
 */

namespace b0rner\solr;

use yii\base\BaseObject;
use yii\base\InvalidArgumentException;


/**
 * QueryBuilder builds an SOLR query based on the specification given as a [[Query]] object.
 */
class QueryBuilder extends BaseObject
{

    /**
     * @var Connection the database connection.
     */
    public $db;


    /**
     * Constructor.
     * @param Connection $connection the database connection.
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($connection, $config = [])
    {
        $this->db = $connection;
        parent::__construct($config);
    }



    /**
     * Generates query from a [[Query]] object.
     * @param Query $query the [[Query]] object from which the query will be generated
     * @return array the query configuration.
     */
    public function build($query)
    {
        $parts = [];

        if ($query->select !== null) {
            is_array($query->select) ? $parts['select'] = $query->select : $parts['select'] = array_map('trim', explode(',', $query->select));
        }

        if (!empty($query->q)) {
            $parts['q'] = $query->q;
        } else {
            $parts['q'] = '*:*';
        }

        // Unlike SQL databases SOLR does not gives back ALL records
        // when limit is not specified (or all() is triggered)
        if ($query->limit !== null) {
            $parts['limit'] = $query->limit;
        } else {
            $parts['limit'] = 10;
        }

        if ($query->where !== null) {
            $parts['where'] = $this->buildWhere($query->where);
        }

        if ($query->offset !== null) {
            $parts['offset'] = $query->offset;
        } else {
            $parts['offset'] = 0;
        }

        if ($query->orderBy !== null) {
            $orderBy = $this->normalizeOrderBy($query->orderBy);
            $parts['orderBy'] = $orderBy;
        } else {
            $parts['orderBy'] = [];
        }

        $parts['edismax'] = $query->edismax;
        $parts['bq'] = $this->buildBq($query->bq);
        $parts['bf'] = $query->bf;
        $parts['boost'] = $query->boost;

        $parts['qf'] = $query->qf;

        $parts['hl'] = $query->hl;
        $parts['hlMethod'] = $query->hlMethod;
        $parts['hlFl'] = $query->hlFl;
        $parts['hlQ'] = $query->hlQ;
        $parts['hlQParser'] = $query->hlQParser;
        $parts['hlRequireFieldMatch'] = $query->hlRequireFieldMatch;
        $parts['hlUsePhraseHighlighter'] = $query->hlUsePhraseHighlighter;
        $parts['hlSnippets'] = $query->hlSnippets;
        $parts['hlFragsize'] = $query->hlFragsize;
        $parts['hlTagPre'] = $query->hlTagPre;
        $parts['hlTagPost'] = $query->hlTagPost;
        $parts['hlEncoder'] = $query->hlEncoder;

        $parts['spellcheck'] = $query->spellcheck;
        $parts['spellcheckQuery'] = $query->spellcheckQuery;
        $parts['spellcheckBuild'] = $query->spellcheckBuild;
        $parts['spellcheckCount'] = $query->spellcheckCount;
        $parts['spellcheckCollate'] = $query->spellcheckCollate;
        $parts['spellcheckOnlyMorePopular'] = $query->spellcheckOnlyMorePopular;
        $parts['spellcheckExtendedResults'] = $query->spellcheckExtendedResults;
        $parts['spellcheckCollateExtendedResults'] = $query->spellcheckCollateExtendedResults;

        $parts['facetCreateFacetField'] = $query->facetCreateFacetField;
        $parts['facetSetField'] = $query->facetSetField;
        $parts['facetSetPrefix'] = $query->facetSetPrefix;
        $parts['facetSetContains'] = $query->facetSetContains;
        $parts['facetSetMatches'] = $query->facetSetMatches;
        $parts['facetSetExcludeTerms'] = $query->facetSetExcludeTerms;
        $parts['facetSetTerms'] = $query->facetSetTerms;
        $parts['facetSetSort'] = $query->facetSetSort;
        $parts['facetSetLimit'] = $query->facetSetLimit;

        $parts['facetCreateFacetQuery'] = $query->facetCreateFacetQuery;
        $parts['facetSetQuery'] = $query->facetSetQuery;

        $parts['facetCreateFacetMultiQuery'] = $query->facetCreateFacetMultiQuery;
        $parts['facetCreateQuery'] = $query->facetCreateQuery;

        $parts['facetCreateFacetRange'] = $query->facetCreateFacetRange;
        $parts['facetSetStart'] = $query->facetSetStart;
        $parts['facetSetEnd'] = $query->facetSetEnd;
        $parts['facetSetGap'] = $query->facetSetGap;

        $parts['group'] = $query->group;
        $parts['groupQueries'] = $query->groupQueries;
        $parts['groupFields'] = $query->groupFields;
        $parts['groupLimit'] = $query->groupLimit;
        $parts['groupOffset'] = $query->groupOffset;
        $parts['groupSort'] = $query->groupSort;
        $parts['groupNumberOfGroups'] = $query->groupNumberOfGroups;
        $parts['groupCachepercentage'] = $query->groupCachepercentage;
        $parts['groupFormat'] = $query->groupFormat;
        $parts['groupTruncate'] = $query->groupTruncate;
        $parts['groupFacet'] = $query->groupFacet;

        $parts['mlt'] = $query->mlt;
        $parts['mltQuery'] = $query->mltQuery;
        $parts['mltStart'] = $query->mltStart;
        $parts['mltRows'] = $query->mltRows;
        $parts['mltFields'] = $query->mltFields;
        $parts['mltSort'] = $query->mltSort;
        $parts['mltMatchInclude'] = $query->mltMatchInclude;
        $parts['mltInterestingTerms'] = $query->mltInterestingTerms;
        $parts['mltMltFields'] = $query->mltMltFields;
        $parts['mltMinimumTermFrequency'] = $query->mltMinimumTermFrequency;
        $parts['mltMinimumDocumentFrequency'] = $query->mltMinimumDocumentFrequency;
        $parts['mltMaximumDocumentFrequency'] = $query->mltMaximumDocumentFrequency;
        $parts['mltMaximumDocumentFrequencyPercentage'] = $query->mltMaximumDocumentFrequencyPercentage;
        $parts['mltMinimumWordLength'] = $query->mltMinimumWordLength;
        $parts['mltMaximumWordLength'] = $query->mltMaximumWordLength;
        $parts['mltMaximumQueryTerms'] = $query->mltMaximumQueryTerms;
        $parts['mltMaximumNumberOfTokens'] = $query->mltMaximumNumberOfTokens;
        $parts['mltBoost'] = $query->mltBoost;
        $parts['mltQueryFields'] = $query->mltQueryFields;

        $parts['queryCacheDuration'] = $query->queryCacheDuration;
        $parts['queryCacheDependency'] = $query->queryCacheDependency;

    	return [
            'queryParts' => $parts,
        ];
    }

    public function buildWhere(array $where)
    {
        // All FilterQueries of this search
        $filterQueries = [];

        foreach ($where as $filterQuery)
        {
            $condition = $filterQuery['condition'];
            $operator = $filterQuery['operator'];

            // Strings of one FilterQuery
            $filterQueryStrings = [];

            foreach ($condition as $field => $value)
            {
                is_array($value) ?: $value = [$value];
                $filterQueryStrings[] = $field . ': (' . implode(' OR ', $value) . ')';
            }

            $filterQueries[] = implode(' ' . strtoupper($operator) . ' ', $filterQueryStrings);
        }

        return $filterQueries;

    }

    public function normalizeOrderBy($orderBy)
    {

        if (is_string($orderBy))
        {
            $orders = [];
            $all_orders = explode(',', $orderBy);

            $orders[ trim($all_orders[0]) ] = strtolower(trim($all_orders[1]));
            return $orders;
        }

        if (is_array($orderBy)) {
            $orders = [];
            foreach ($orderBy as $field => $order) {
                $orders[$field] = strtolower($order);
            }

            return $orders;
        }

        return [];
    }

    /**
     * Create one or multiple bq calls
     * @var string|array what to boost
     * @return array
     */
    public function buildBq($bq)
    {
        $boosts = [];

        if (is_string($bq))
        {
            $all_bqs = explode(':', $bq);
            $boosts[ trim($all_bqs[0]) ] = [trim($all_bqs[1])];
        }

        if (is_array($bq)) {
            foreach ($bq as $boost) {
                foreach ($boost as $field => $query) {
                    if (!array_key_exists($field, $boosts)) {
                        $boosts[$field][] = $query;
                    } else {
                        array_push($boosts[$field], $query);
                    }
                }
            }
        }

        if (!empty($boosts)) {
            foreach ($boosts as $field => $queries) {
                foreach ($queries as $query) {
                    $tmp[] = $field . ':' . $query;
                }
                $my_boosts[] = implode(' OR ', $tmp);
            }
            return $my_boosts;
        }

        return [];

    }

}