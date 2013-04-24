<?php

namespace YiiElasticSearch;

use \CActiveRecord as CActiveRecord;
use \Yii as Yii;

/**
 * Class SearchableActiveRecord
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class SearchableActiveRecord extends CActiveRecord implements SearchableInterface
{
    /**
     * @var bool whether or not to automatically index and delete documents in elastic search
     */
    public $autoIndex = true;

    /**
     * @var Connection the elasticSearchConnection to use for this document
     */
    protected $elasticSearchConnection;

    /**
     * @var string the name of the index
     */
    protected $indexName;

    /**
     * @var string the name of the type
     */
    protected $typeName;

    /**
     * @var float the document score
     */
    protected $score;

    /**
     * Get the elastic search elasticSearchConnection to use for this document
     * @return Connection the elastic search elasticSearchConnection
     * @throws \Exception if no elasticSearchConnection is specified
     */
    public function getElasticSearchConnection()
    {
        if ($this->elasticSearchConnection === null) {
            if (Yii::app()->hasComponent('elasticSearch'))
                return Yii::app()->getComponent('elasticSearch');
            throw new \Exception(__CLASS__." expects an 'elasticSearch' application component");
        }
        return $this->elasticSearchConnection;
    }

    /**
     * Sets the elastic search elasticSearchConnection to use for this document
     * @param Connection $connection
     */
    public function setElasticSearchConnection(Connection $connection)
    {
        $this->elasticSearchConnection = $connection;
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
     * Set the document score
     * @param float $score
     */
    public function setScore($score)
    {
        $this->score = $score;
    }

    /**
     * @return float the document score
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * Creates a document containing data that
     * can be added to the elastic search index
     * @return DocumentInterface the indexable document
     */
    public function createDocument()
    {
        $document = $this->createDocumentInstance();
        $document->setId($this->getPrimaryKey());
        foreach($this->attributeNames() as $name)
            $document->{$name} = $this->{$name};
        return $document;
    }

    /**
     * Creates a document instance
     * @return Document the document instance
     */
    protected function createDocumentInstance()
    {
        $document = new Document();
        $document->setElasticSearchConnection($this->elasticSearchConnection);
        $document->setIndexName($this->getIndexName());
        $document->setTypeName($this->getTypeName());
        return $document;
    }

    /**
     * Apply the attributes from a document to a new object.
     *
     * @param DocumentInterface $document the document that is providing the data
     *
     * @return SearchableInterface the new object with the attributes applied
     */
    public function populateFromDocument(DocumentInterface $document)
    {
        $model = new static; /* @var SearchableActiveRecord $model */
        $model->setPrimaryKey($document->getId());
        if ($document instanceof SearchResult)
            $model->setScore($document->getScore());
        foreach($document->getSource() as $attribute => $value)
            $model->{$attribute} = $value;
        return $model;
    }

    /**
     * Invoked after the model is saved, adds the model to elastic search
     */
    protected function afterSave()
    {
        if ($this->autoIndex)
            $this->getElasticSearchConnection()->index($this->createDocument());
        parent::afterSave();
    }

    /**
     * Invoked after the model is deleted, removes the model from elastic search too.
     */
    protected function afterDelete()
    {
        if ($this->autoIndex)
            $this->getElasticSearchConnection()->delete($this->createDocument());
        parent::afterDelete();
    }


}
