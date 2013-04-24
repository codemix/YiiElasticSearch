<?php

namespace YiiElasticSearch;

/**
 * Represents the response of a query from elastic search
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class ResultSet
{
    /**
     * @var array the raw response from elastic search
     */
    public $raw;

    /**
     * @var Criteria the criteria that was used in the query
     */
    public $criteria;

    /**
     * Initialize the result set
     * @param Criteria $criteria
     * @param array $raw
     */
    public function __construct(Criteria $criteria, array $raw)
    {
        $this->criteria = $criteria;
        $this->raw = $raw;
    }

    /**
     * @return array the facets
     */
    public function getFacets()
    {
        return $this->raw['facets'];
    }


    /**
     * Gets the search results
     * @return SearchResult[] the search results
     */
    public function getResults()
    {
        $hits = array();
        foreach($this->raw['hits']['hits'] as $hit) {
            $hits[] = new SearchResult($this, $hit);
        }
        return $hits;
    }

    /**
     * @return int the number of returned results
     */
    public function countResults()
    {
        return count($this->raw['hits']['hits']);
    }

    /**
     * @return int the total number of results
     */
    public function getTotal()
    {
        return $this->raw['hits']['total'];
    }
}
