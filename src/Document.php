<?php

namespace YiiElasticSearch;

use \Yii as Yii;

/**
 * Represents a document that can be added to and retrieved from elastic search
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class Document implements DocumentInterface
{
    /**
     * @var Connection the elasticSearchConnection to use for this document
     */
    protected $connection;

    /**
     * @var mixed the id of the document
     */
    protected $id;

    /**
     * @var string the name of the index
     */
    protected $indexName;

    /**
     * @var string the name of the type
     */
    protected $typeName;

    /**
     * @var array the document data
     */
    protected $source = array();

    /**
     * Get the elastic search elasticSearchConnection to use for this document
     * @return Connection the elastic search elasticSearchConnection
     * @throws \Exception if no elasticSearchConnection is specified
     */
    public function getElasticSearchConnection()
    {
        if ($this->connection === null) {
            if (Yii::app()->hasComponent('elasticSearch'))
                return Yii::app()->getComponent('elasticSearch');
            throw new \Exception(__CLASS__." expects an 'elasticSearch' application component");
        }
        return $this->connection;
    }

    /**
     * Sets the elastic search elasticSearchConnection to use for this document
     * @param \YiiElasticSearch\Connection $connection
     */
    public function setElasticSearchConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the name of the elastic search index
     * that this document is stored in.
     * @return string the name of the index
     */
    public function getIndexName()
    {
        return $this->indexName;
    }

    /**
     * @param string $indexName
     */
    public function setIndexName($indexName)
    {
        $this->indexName = $indexName;
    }

    /**
     * Get the name of the elastic search type
     * that this document belongs to within an index
     */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /**
     * @param string $typeName
     */
    public function setTypeName($typeName)
    {
        $this->typeName = $typeName;
    }

    /**
     * Gets the document ID
     * @return mixed the document ID
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }


    /**
     * Get the data that should be indexed
     * @return array the indexable document data
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param array $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * Get the named field value     *
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
     * Sets the named field value
     * @param string $name the name of the field to set
     * @param mixed $value the value to set
     */
    public function __set($name, $value)
    {
        $this->source[$name] = $value;
    }

    /**
     * Determine whether or not the document has a field with the fiven name
     * @param string $name the field name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->source[$name]);
    }

    /**
     * Removes the named field.
     * @param string $name the name of the field to remove
     */
    public function __unset($name)
    {
        unset($this->source[$name]);
    }


}
