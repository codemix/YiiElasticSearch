<?php

namespace YiiElasticSearch;

/**
 * Represents a search request to elasticsearch
 *
 * This class is mainly an OO container for search parameters.
 * See http://www.elasticsearch.org/guide/reference/api/search/
 * for available parameters.
 *
 * You can set arbitrary properties:
 *
 *      $search = new YiiElasticSearch\Search;
 *      $search->query = array(
 *          'match_all' => array(),
 *      );
 *      $search->filter = array(
 *          'term' => array('user'=>'foo'),
 *      );
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class Search implements \ArrayAccess, \Countable
{
    /**
     * @var string the name of the index to search within
     */
    public $index;

    /**
     * @var string the name of the document type within the index
     */
    public $type;

    /**
     * @var array the internal data storage
     */
    private $data = array();

    /**
     * @param string|null $index the name of the index to search within
     * @param string|null $type the name of the document type
     * @param array $data the query data
     */
    public function __construct($index = null, $type = null, $data = array())
    {
        $this->index = $index;
        $this->type = $type;
        $this->data = $data;
    }

    /**
     * @return array an array representation of the query
     */
    public function toArray()
    {
        return $this->data;
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
        return count($this->data);
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
        return isset($this->data[$offset]);
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
        return $this->data[$offset];
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
        $this->data[$offset] = $value;
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
        unset($this->data[$offset]);
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
        if (isset($this->data[$name]))
            return $this->data[$name];
        throw new \Exception(__CLASS__.' has no such property: '.$name);
    }

    /**
     * Sets the named field value
     * @param string $name the name of the field to set
     * @param mixed $value the value to set
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Determine whether or not the search has a field with the given name
     * @param string $name the field name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Removes the named field.
     * @param string $name the name of the field to remove
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }
}
