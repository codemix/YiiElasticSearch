<?php

namespace YiiElasticSearch;

/**
 * Represents an elastic search result
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class SearchResult extends Document
{
    /**
     * @var ResultSet the result set this result is part of
     */
    protected $resultSet;

    /**
     * @var float the result score
     */
    protected $score;

    /**
     * Initialize the search result
     * @param ResultSet $resultSet the result set this is a part of
     * @param array $result the result data
     */
    public function __construct(ResultSet $resultSet, array $result)
    {
        $this->resultSet = $resultSet;
        $this->indexName = $result['_index'];
        $this->typeName = $result['_type'];
        $this->id = $result['_id'];
        $this->score = $result['_score'];
        $this->source = $result['_source'];
    }

    /**
     * Gets the result score
     * @return float
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * Gets the result set this result is part of
     * @return \YiiElasticSearch\ResultSet
     */
    public function getResultSet()
    {
        return $this->resultSet;
    }


}
