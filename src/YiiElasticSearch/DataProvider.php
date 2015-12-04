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
     * @var CActiveRecord|array a model or an array of models implementing the searchable interface
     */
    public $model;

    /**
     * @var string|null optional name of model attribute to use a attribute key instead of the primary key
     */
    public $keyAttribute;

    /**
     * @var Search the search parameters
     */
    protected $_search;

    /**
     * @var ResultSet the search result set
     */
    protected $resultSet;

    /**
     * @var mixed the fetched data
     */
    protected $fetchedData;

    /**
     * @var array model classnames indexed by `$elasticIndex-$elasticType`
     */
    protected $_classes = array();

    /**
     * @var array name of elasticsearch indices
     */
    protected $_indices = array();

    /**
     * @var array name of elasticsearch types
     */
    protected $_types = array();

    /**
     * Initialize the data provider
     * @param CActiveRecord|string|array $model the search model or classname.
     * This can also be an array of models or names in which case the search
     * is performed over all respective indices.
     * This can also be the classname or an array of classnames or a mix of either.
     * @param array $config the data provider configuration
     */
    public function __construct($model, $config = array())
    {
        if (is_array($model)) {
            $this->model = array();
            foreach ($model as $value) {
                if (is_string($value)) {
                    $value = new $value;
                }
                $this->_classes[$value->elasticIndex.'-'.$value->elasticType] = get_class($value);
                $this->_indices[] = $value->elasticIndex;
                $this->_types[] = $value->elasticType;
                $this->model[] = $value;
            }
        } else {
            $this->model = is_string($model) ? new $model : $model;
            $this->_indices[] = $this->model->elasticIndex;
            $this->_types[] = $this->model->elasticType;
        }

        foreach($config as $attribute => $value)
            $this->{$attribute} = $value;
    }

    /**
     * @param \YiiElasticSearch\Search|array $search a Search object or an array with search parameters
     */
    public function setSearch($search)
    {
        if (is_array($search)) {
            $search = new Search(
                $this->model->elasticIndex,
                $this->model->elasticType,
                $search
            );
        }

        if(!$search->index) {
            $search->index = implode(',', $this->_indices);
        }

        if(!$search->type) {
            $search->type = implode(',', $this->_types);
        }

        $this->_search = $search;
    }

    /**
     * @return \YiiElasticSearch\Search
     */
    public function getSearch()
    {
        if ($this->_search === null) {
            $this->_search = new Search(
                implode(',', $this->_indices),
                implode(',', $this->_types),
                array(
                    'query' => array(
                        'match_all' => array()
                    )
                )
            );
        }
        return $this->_search;
    }

    /**
     * @return array the facets
     */
    public function getFacets()
    {
        if($this->resultSet===null) {
            $this->fetchData();
        }

        return $this->resultSet->getFacets();
    }

    /**
     * @return array list of data items
     */
    protected function fetchData()
    {
        if($this->fetchedData===null) {
            $search = $this->_search;
            if (($pagination = $this->getPagination()) !== false) {
                $pagination->validateCurrentPage = false;
                $search['from'] = $pagination->getOffset();
                $search['size'] = $pagination->pageSize;
            }

            $model = is_array($this->model) ? $this->model[0] : $this->model;

            $this->resultSet = $model->getElasticConnection()->search($search);

            $this->fetchedData = array();
            foreach($this->resultSet->getResults() as $result) {
                $key = $result->getIndex().'-'.$result->getType();
                $modelClass = $this->_classes[$key];
                $model = new $modelClass;
                $model->setIsNewRecord(false);
                $model->parseElasticDocument($result);
                $this->fetchedData[] = $model;
            }

            if($pagination!==false)
            {
                $pagination->setItemCount($this->getTotalItemCount());
            }
        }
        return $this->fetchedData;
    }

    /**
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
     * @return integer the total number of data items.
     */
    protected function calculateTotalItemCount()
    {
        if($this->resultSet===null) {
            $this->fetchData();
        }

        return $this->resultSet->getTotal();
    }
}
