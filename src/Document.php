<?php

namespace YiiElasticSearch;

use \Yii as Yii;

/**
 * Represents a document that can be added to and retrieved from elastic search
 *
 * Arbitrary attributes can be set on a document object and will be indexed and
 * read back from the index.
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class Document implements DocumentInterface
{
    /**
     * @var Connection the elasticSearchConnection to use for this document
     */
    protected $_connection;

    /**
     * @var mixed the id of the document
     */
    protected $_id;

    /**
     * @var string the name of the index
     */
    protected $_index;

    /**
     * @var string the name of the type
     */
    protected $_type;

    /**
     * @var array the document data
     */
    protected $_source = array();

    /**
     * @return Connection the elasticsearch connection to use for this document
     * @throws \Exception if no connection is specified
     */
    public function getConnection()
    {
        if ($this->_connection === null) {
            if (Yii::app()->hasComponent('elasticSearch'))
                return Yii::app()->getComponent('elasticSearch');
            throw new \Exception(__CLASS__." expects an 'elasticSearch' application component");
        }
        return $this->_connection;
    }

    /**
     * @param \YiiSearch\Connection $connection to use for this document
     */
    public function setConnection($connection)
    {
        $this->_connection = $connection;
    }

    /**
     * @return string the name of the index that this document is stored in, inlcuding any indexPrefix
     */
    public function getIndex()
    {
        return $this->_index;
    }

    /**
     * @param string the name of the index that this document is stored in, including any indexPrefix
     */
    public function setIndex($index)
    {
        $this->_index = $index;
    }

    /**
     * @return string the name of the type that this document is stored as
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @param string the name of the type that this document is stored as
     */
    public function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * @return mixed the ID of this document in the elasticsearch index
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param mixed the ID of this document in the elasticsearch index
     */
    public function setId($id)
    {
        $this->_id = $id;
    }


    /**
     * @return array the data that should be indexed
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * @param array the data that should be indexed
     */
    public function setSource($data)
    {
        $this->_source = $data;
    }

    /**
     * @param string $name the name of the field to access
     * @return mixed the value
     *
     * @throws \Exception if no source field exists with the given name
     */
    public function __get($name)
    {
        if (isset($this->source[$name]))
            return $this->source[$name];
        throw new \Exception(__CLASS__.' has no such property: '.$name);
    }

    /**
     * @param string $name the name of the field to set
     * @param mixed $value the value to set
     */
    public function __set($name, $value)
    {
        $this->source[$name] = $value;
    }

    /**
     * @param string $name the field name
     * @return bool wether the document has a field with the given name
     */
    public function __isset($name)
    {
        return isset($this->source[$name]);
    }

    /**
     * Removes the named field
     * @param string $name the name of the field to remove
     */
    public function __unset($name)
    {
        unset($this->source[$name]);
    }


}
