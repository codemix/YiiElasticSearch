<?php

namespace YiiElasticSearch;

use \CDataProvider as CDataProvider;

/**
 * Data provider that can retrieve results from elastic search.
 *
 * <pre>
 *  $dataProvider = new YiiElasticSearch\DataProvider(MySearchableModel::model());
 *  CVarDumper::dump($dataProvider->getData());
 * </pre>
 *
 * @package YiiElasticSearch
 */
class DataProvider extends CDataProvider
{
    /**
     * @var SearchableInterface a model implementing the searchable interface
     */
    public $model;

    /**
     * @var Criteria the search criteria
     */
    protected $criteria;

    /**
     * @var ResultSet the search result set
     */
    protected $resultSet;

    /**
     * Initialize the data provider
     * @param SearchableInterface $model the search model
     * @param array $config the data provider configuration
     */
    public function __construct($model, $config = array())
    {
        if (is_string($model))
            $model = new $model;
        $this->model = $model;
        foreach($config as $attribute => $value)
            $this->{$attribute} = $value;
    }

    /**
     * @param \YiiElasticSearch\Criteria $criteria
     */
    public function setCriteria($criteria)
    {
        if (is_array($criteria))
            $criteria = new Criteria(
                $this->model->getIndexName(),
                $this->model->getTypeName(),
                $criteria
            );

        $this->criteria = $criteria;
    }

    /**
     * @return \YiiElasticSearch\Criteria
     */
    public function getCriteria()
    {
        if ($this->criteria === null) {
            $this->criteria = new Criteria(
                $this->model->getIndexName(),
                $this->model->getTypeName(),
                array(
                    'query' => array(
                        'match_all' => array()
                    )
                )
            );
        }
        return $this->criteria;
    }




    /**
     * Fetches the data from the persistent data storage.
     * @return array list of data items
     */
    protected function fetchData()
    {
        $criteria = $this->criteria;
        if (($pagination = $this->getPagination()) !== false) {
            $criteria['from'] = $pagination->getOffset();
            $criteria['size'] = $pagination->pageSize;
        }


        $results = $this->model->getElasticSearchConnection()->search($criteria);

        $this->resultSet = $results;
        $this->setTotalItemCount($results->getTotal());
        $data = array();
        foreach($results->getResults() as $result)
            $data[] = $this->model->populateFromDocument($result);
        return $data;
    }

    /**
     * Fetches the data item keys from the persistent data storage.
     * @return array list of data item keys.
     */
    protected function fetchKeys()
    {
        $keys=array();
        foreach($this->getData() as $i=>$data)
        {
            $key=$this->keyAttribute===null ? $data->getPrimaryKey() : $data->{$this->keyAttribute};
            $keys[$i]=is_array($key) ? implode(',',$key) : $key;
        }
        return $keys;
    }

    /**
     * Calculates the total number of data items.
     * @return integer the total number of data items.
     */
    protected function calculateTotalItemCount()
    {
        return 99999999;
    }

}
