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
class Document implements DocumentInterface, \ArrayAccess, \Countable, \IteratorAggregate
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
        if (isset($this->_source[$name]))
            return $this->_source[$name];
        throw new \Exception(__CLASS__.' has no such property: '.$name);
    }

    /**
     * @param string $name the name of the field to set
     * @param mixed $value the value to set
     */
    public function __set($name, $value)
    {
        $this->_source[$name] = $value;
    }

    /**
     * @param string $name the field name
     * @return bool wether the document has a field with the given name
     */
    public function __isset($name)
    {
        return isset($this->_source[$name]);
    }

    /**
     * Removes the named field
     * @param string $name the name of the field to remove
     */
    public function __unset($name)
    {
        unset($this->_source[$name]);
    }

    /**
     * @return array an array representation of the document
     */
    public function toArray()
    {
        return $this->_source;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->_source);
    }


    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return isset($this->_source[$offset]);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->_source[$offset];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->_source[$offset] = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->_source[$offset]);
    }

    /**
     * @return Iterator as required for IteratorAggrete
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->_source);
    }
}
