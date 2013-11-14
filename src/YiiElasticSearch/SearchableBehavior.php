<?php

namespace YiiElasticSearch;

use \CActiveRecordBehavior as CActiveRecordBehavior;
use \Yii as Yii;

/**
 * This behavior can be attached to an ActiveRecord to automatically
 * index the record in elasticsearch.
 *
 * @property Connection $elasticConnection
 * @property string $elasticIndex
 * @property string $elasticType
 * @property string $elasticScore
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class SearchableBehavior extends CActiveRecordBehavior
{
    /**
     * @var bool whether to automatically index and delete documents in elastic search. Default is true.
     */
    public $elasticAutoIndex = true;

    /**
     * @var Connection the elasticsearch connection to use for this document
     */
    protected $_elasticConnection;

    /**
     * @var float the document score
     */
    protected $_score;

    /**
     * @return Connection the elasticsearch connection to use for the record
     * @throws \Exception if no elasticsearch is specified
     */
    public function getElasticConnection()
    {
        if ($this->_elasticConnection === null) {
            if (Yii::app()->hasComponent('elasticSearch'))
                $this->_elasticConnection = Yii::app()->getComponent('elasticSearch');
            else
                throw new \Exception(__CLASS__." expects an 'elasticSearch' application component");
        }
        return $this->_elasticConnection;
    }

    /**
     * @return string the index where this record will be stored. Default is the (sanitized)
     * application name. To customize define $elasticIndex or getElasticIndex() in the record.
     */
    public function getElasticIndex()
    {
        return preg_replace('/[^a-z0-9]/','',strtolower(Yii::app()->name));
    }

    /**
     * @return string the type under which this record will be stored. Default is the lower case
     * class name of the active record. To customize define $elasticType or getElasticType() in the record.
     */
    public function getElasticType()
    {
        return strtolower(get_class($this->owner));
    }

    /**
     * @return float how much this record is relevant to the search query. Only set on queried records.
     */
    public function getElasticScore()
    {
        return $this->_score;
    }

    /**
     * @param float $score how much this record is relevant to the search query. Do not set manually.
     */
    public function setElasticScore($score)
    {
        $this->_score = $score;
    }

    /**
     * Add/Update this document to/in the elasticsearch index
     */
    public function indexElasticDocument()
    {
        $this->getElasticConnection()->index($this->createElasticDocument());
    }

    /**
     * @param DocumentInterface $document the document where the indexable data must be applied to.
     * Override this method in a record to define which data should get indexed. By default all
     * record attributes get indexed.
     */
    public function populateElasticDocument(DocumentInterface $document)
    {
        $document->setId($this->owner->getPrimaryKey());
        foreach($this->owner->attributeNames() as $name)
            $document->{$name} = $this->owner->{$name};
    }

    /**
     * @param DocumentInterface $document the document that is providing the data for this record.
     * Override this method to apply custom data from a search result to a new record.
     */
    public function parseElasticDocument(DocumentInterface $document)
    {
        $this->owner->setPrimaryKey($document->getId());
        if ($document instanceof SearchResult)
            $this->owner->setElasticScore($document->getScore());
        foreach($document->getSource() as $attribute => $value)
            $this->owner->{$attribute} = $value;
    }

    /**
     * @return Document a new elasticsearch document instance
     */
    protected function createElasticDocument()
    {
        $document = new Document();
        $document->setConnection($this->_elasticConnection);
        $document->setIndex($this->_elasticConnection->indexPrefix.$this->owner->elasticIndex);
        $document->setType($this->owner->elasticType);
        $this->owner->populateElasticDocument($document);
        return $document;
    }

    /**
     * Invoked after the model is saved, adds the model to elastic search
     */
    public function afterSave($event)
    {
        if ($this->elasticAutoIndex)
            $this->indexElasticDocument();
        parent::afterSave($event);
    }

    /**
     * Invoked after the model is deleted, removes the model from elastic search too.
     */
    public function afterDelete($event)
    {
        if ($this->elasticAutoIndex)
            $this->getElasticConnection()->delete($this->createElasticDocument());
        parent::afterDelete($event);
    }
}
