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
     * @return Connection the elasticsearch connection to use for this document
     */
    public function getConnection();

    /**
     * @return string the name of the index that this document is stored in, inlcuding any indexPrefix
     */
    public function getIndex();

    /**
     * @return string the name of the index that this document is stored in, including any indexPrefix
     */
    public function getType();

    /**
     * @return mixed ID of this document's parent document in the elasticsearch index
     */
    public function getParent();

    /**
     * @param mixed $parent ID of this document's parent document in the elasticsearch index
     */
    public function setParent($parent);

    /**
     * @return mixed the ID of this document in the elasticsearch index
     */
    public function getId();

    /**
     * @param mixed $id the ID of this document in the elasticsearch index
     */
    public function setId($id);

    /**
     * @return array the data that should be indexed
     */
    public function getSource();

    /**
     * @return string the url of this document
     */
    public function getUrl();
}
