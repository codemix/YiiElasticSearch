<?php
namespace YiiElasticSearch;

use \Yii as Yii;
use \CConsoleCommand as CConsoleCommand;
use \CActiveRecord as CActiveRecord;

/**
 * This is the maintenance command for the elasticSearch component.
 *
 * @author Michael Härtl <haertl.mike@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class ConsoleCommand extends CConsoleCommand
{
    /**
     * @var string name of default action.
     */
    public $defaultAction = 'help';

    /**
     * @var string name of model
     */
    public $model;

    /**
     * @var string name of index
     */
    public $index;

    /**
     * @var string name of type
     */
    public $type;

    /**
     * @var bool whether to supress any output from this command
     */
    public $quiet = false;

    /**
     * @var bool whether to be more verbose
     */
    public $verbose = false;

    /**
     * @var bool whether to only perform the command if target does not exist. Default is false.
     */
    public $skipExisting = false;

    /**
     * @return string help for this command
     */
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic elastic [action] [parameter]

DESCRIPTION
  This is the maintenance command for the elasticsearch component. It
  allows to manage or list entries in the elasticsearch index.

ACTIONS

  * index --model=<model> [--skipExisting]

    Add all models <model> to the index. This will replace any previous
    entries for this model in the index. Index and type will be auto-detected
    from the model class unless --index or --type is set explicitely.
    If --skipExisting is used, no action is performed if there are already
    documents indexed under this type.


  * map --model=<model> --map=<filename> [--skipExisting]
    map --index=<index> --map=<filename> [--skipExisting]

    Create a mapping in the index specified with the <index> or implicitly
    through the <model> parameter. The mapping must be available from a JSON
    file in <filename> where the JSON must have this form:

        {
            "tweet" : {
                "name" : {"type" : "string"},
                ...
            },
            ...
        }

    If --skipExisting is used, no action is performed if there's are already
    a mapping for this index.


  * list [--limit=10] [--offset=0]
    list [--model=<name>] [--limit=10] [--offset=0]
    list [--index=<name>] [--type=<type>] [--limit=10] [--offset=0]

    List all entries in elasticsearch. If a model or an index (optionally with
    a type) is specified only entries matching index and type of the model will be listed.


  * delete --model=<name> [--id=<id>]

    Delete a document from an index. If no <id> is specified the whole
    index will be deleted.


  * help

    Show this help

EOD;
    }

    /**
     * Index the given model in elasticsearch
     */
    public function actionIndex()
    {
        $n = 0;

        $model  = $this->getModel();
        $table  = $model->tableName();
        $count  = $model->count();
        $step   = $count > 5 ? floor($count/5) : 1;
        $index  = $model->elasticIndex;
        $type   = $model->elasticType;

        if($this->skipExisting && !Yii::app()->elasticSearch->typeEmpty("$index/$type")) {
            $this->message("'$index/$type' is not empty. Skipping index command.");
            return;
        }

        $this->message("Adding $count '{$this->model}' records from table '$table' to index '$index'\n 0% ", false);

        // We use a data reader to keep memory footprint low
        $reader = Yii::app()->db->createCommand("SELECT * FROM $table")->query();
        while(($row = $reader->read())!==false) {
            $record = $model->populateRecord($row);
            $record->indexElasticDocument();
            $n++;

            if(($n % $step)===0) {
                $percent = 20*$n / $step;

                if($percent < 100) {
                    $this->message((20*$n/$step).'% ',false);
                }
            }
        }

        $this->message('100%');
    }

    /**
     * @param string $map the path to the JSON map file
     * @param bool $noDelete whether to supress index deletion
     */
    public function actionMap($map)
    {
        $index      = $this->getIndex();
        $file       = file_get_contents($map);
        $mapping    = json_decode($file);
        $elastic    = Yii::app()->elasticSearch;

        if($elastic->mappingExists($index)) {
            if($this->skipExisting) {
                $this->message("Mapping for '$index' exists. Skipping map command.");
                return;
            } else {
                $this->message("Deleting '$index' ... ",false);
                $this->performRequest($elastic->client->delete($index));
                $this->message("done");
            }
        }

        if($mapping===null) {
            $this->usageError("Invalid JSON in $map");
        }

        $body = json_encode(array(
            'mappings' => $mapping,
        ));

        $this->performRequest($client->put($index, array("Content-type" => "application/json"))->setBody($body));

        $this->message("Created mappings for '$index' from file in '$map'");
    }


    /**
     * List documents in elasticsearch
     *
     * @param int $limit how many documents to show. Default is 10.
     * @param int $offset at which document to start. Default is 0.
     */
    public function actionList($limit=10, $offset=0)
    {
        $search         = new Search;
        $search->size   = $limit;
        $search->from   = $offset;

        if(($model = $this->getModel(false))!==null) {
            $search->index  = $model->elasticIndex;
            $search->type   = $model->elasticType;
        } else {
            if(($index = $this->getIndex(false))!==null) {
                $search->index = $index;
            }
            if(($type = $this->getType(false))!==null) {
                $search->type = $type;
            }
        }

        $search->query = array(
            'match_all' => array(),
        );

        try {
            $result = Yii::app()->elasticSearch->search($search);
        } catch (\CException $e) {
           $this->message($e->getMessage());
            Yii::app()->end(1);
        }

        $this->message("Showing {$result->count} of {$result->total} found documents");
        $this->message('-------------------------------------------------------');
        foreach($result->results as $document) {
            $this->renderDocument($document);
        }
    }

    /**
     * Delete a document or a complete index from elasticsearch
     *
     * @param string $model name of the ActiveRecord to delete from the index
     * @param int|null $id of the record to delete. Optional.
     */
    public function actionDelete($model,$id=null)
    {
        $name   = $model;
        $model  = CActiveRecord::model($name);
        $index  = $model->elasticIndex;
        $type   = $model->elasticType;
        $url    = $index.'/'.$type. ($id===null ? '' : '/'.$id);

        $this->performRequest(Yii::app()->elasticSearch->client->delete($url));

        if($id===null) {
            $this->message("Deleted index $index");
        } else {
            $this->message("Deleted ID $id from index $index");
        }

    }

    /**
     * Show help
     */
    public function actionHelp()
    {
        echo $this->getHelp();
    }

    /**
     * Output a message
     *
     * @param string $text message text
     * @param bool $newline whether to append a newline. Default is true.
     */
    protected function message($text, $newline=true)
    {
        if(!$this->quiet) {
            echo $text . ($newline ? "\n" : '');
        }
    }

    /**
     * Output a document
     *
     * @param Document $document the document to render
     */
    protected function renderDocument($document)
    {
        $this->message("Index   : {$document->getIndex()}");
        $this->message("Type    : {$document->getType()}");
        $this->message("ID      : {$document->getId()}");
        if($this->verbose) {
            $this->message('.......................................................');
            foreach($document as $key=>$value) {
                $this->message(sprintf(' %20s : %20s',$key,$this->parseValue($value)));
            }
        }

        $this->message('-------------------------------------------------------');
    }

    /**
     * @param mixed $value any document value
     * @return string the parsed value ready for output
     */
    protected function parseValue($value)
    {
        if(is_array($value)) {
            return 'Array (...)';
        } else {
            return $value;
        }
    }

    /**
     * @param Guzzle\EntityEnclosingRequestInterface $request
     */
    protected function performRequest($request)
    {
        try {
            $request->send();
        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $body = $e->getResponse()->getBody(true);
            if(($msg = json_decode($body))!==null) {
                $this->message($msg->error);
            } else {
                $this->message($e->getMessage());
            }
            Yii::app()->end(1);
        }
        catch(\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $this->message($e->getResponse()->getBody(true));
            Yii::app()->end(1);
        }
    }

    /**
     * @param bool $required whether a model is required
     * @return CActiveRecord|null the model instance
     */
    protected function getModel($required=true)
    {
        if(!$this->model) {
            if($required) {
                $this->usageError("Model must be supplied with --model.");
            } else {
                return null;
            }
        }

        return CActiveRecord::model($this->model);
    }

    /**
     * @param bool $required whether a index is required
     * @return string|null the index name as set with --index or implicitly through --model
     */
    protected function getIndex($required=true)
    {
        if(!$this->model && !$this->index) {
            if($required) {
                $this->usageError("Either --model or --index must be supplied.");
            } else {
                return null;
            }
        }

        return $this->index ? $this->index : $this->getModel()->elasticIndex;
    }

    /**
     * @param whether a type is required
     * @return string|null the type name as set with --type or implicitly through --model
     */
    protected function getType($required=true)
    {
        if(!$this->model && !$this->type) {
            if($required) {
                $this->usageError("Either --model or --type must be supplied.");
            } else {
                return null;
            }
        }

        return $this->type ? $this->type : $this->getModel()->elasticType;
    }
}
