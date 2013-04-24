<?php

namespace YiiElasticSearch;

/**
 * Class DocumentInterface
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
interface DocumentInterface
{

    /**
     * Get the elastic search elasticSearchConnection to use for this document
     * @return Connection the elastic search elasticSearchConnection
     */
    public function getElasticSearchConnection();

    /**
     * Get the name of the elastic search index
     * that this document is stored in.
     * @return string the name of the index
     */
    public function getIndexName();

    /**
     * Get the name of the elastic search type
     * that this document belongs to within an index
     */
    public function getTypeName();

    /**
     * Gets the document ID
     * @return mixed the document ID
     */
    public function getId();

    /**
     * Get the data that should be indexed
     * @return array the indexable document data
     */
    public function getSource();
}
