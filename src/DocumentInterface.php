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
     * @param string the name of the index that this document is stored in, including any indexPrefix
     */
    public function getType();

    /**
     * @return mixed the ID of this document in the elasticsearch index
     */
    public function getId();

    /**
     * @return array the data that should be indexed
     */
    public function getSource();
}
