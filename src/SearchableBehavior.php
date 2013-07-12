<?php

namespace YiiElasticSearch;

use \CActiveRecordBehavior as CActiveRecordBehavior;
use \Yii as Yii;

/**
 * This behavior can be attached to an ActiveRecord to automatically
 * index the record in elasticsearch.
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
                return Yii::app()->getComponent('elasticSearch');
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
     * @param float how much this record is relevant to the search query. Do not set manually.
     */
    public function setElasticScore($score)
    {
        $this->_score = $score;
    }

    /**
     * @return DocumentInterface the indexable document to be added to the elasticsearch index.
     * Override this method in a record to define which data should get indexed. By default all
     * record attributes are added to the index.
     */
    public function createElasticDocument()
    {
        $document = $this->createDocumentInstance();
        $document->setId($this->getPrimaryKey());
        foreach($this->attributeNames() as $name)
            $document->{$name} = $this->{$name};
        return $document;
    }

    /**
     * Apply the attributes from a elasticsearch document to a new owner object
     *
     * Override this method to apply custom data from a search result to a new record.
     *
     * @param DocumentInterface $document the document that is providing the data
     * @return CActiveRecord a new object with the attributes applied.
     */
    public function populateFromElasticDocument(DocumentInterface $document)
    {
        $class = get_class($this->owner);
        $model = new $class; /* @var CActiveRecord $model */
        $model->setPrimaryKey($document->getId());
        if ($document instanceof SearchResult)
            $model->setElasticScore($document->getScore());
        foreach($document->getSource() as $attribute => $value)
            $model->{$attribute} = $value;
        return $model;
    }

    /**
     * @return Document a new elasticsearch document instance
     */
    protected function createDocumentInstance()
    {
        $document = new Document();
        $document->setElasticConnection($this->_elasticConnection);
        $document->setIndexName($this->_elasticConnection->indexPrefix.$this->owner->elasticIndex);
        $document->setTypeName($this->owner->elasticType);
        return $document;
    }

    /**
     * Invoked after the model is saved, adds the model to elastic search
     */
    protected function afterSave()
    {
        if ($this->autoIndex)
            $this->getElasticConnection()->index($this->createDocument());
        parent::afterSave();
    }

    /**
     * Invoked after the model is deleted, removes the model from elastic search too.
     */
    protected function afterDelete()
    {
        if ($this->autoIndex)
            $this->getElasticConnection()->delete($this->createDocument());
        parent::afterDelete();
    }


}
