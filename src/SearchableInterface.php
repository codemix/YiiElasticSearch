<?php

namespace YiiElasticSearch;

/**
 * Classes that are searchable and indexable should implement this interface
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
interface SearchableInterface
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
     * Creates a document containing data that
     * can be added to the elastic search index
     * @return DocumentInterface the indexable document
     */
    public function createDocument();


    /**
     * Apply the attributes from a document to a new object.
     *
     * @param DocumentInterface $document the document that is providing the data
     *
     * @return SearchableInterface the new object with the attributes applied
     */
    public function populateFromDocument(DocumentInterface $document);
}
