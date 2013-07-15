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
     * @var bool wether to supress any output from this command
     */
    public $quiet = false;

    /**
     * @var bool wether to be more verbose
     */
    public $verbose = false;

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

  index --model=<name>

    Add all models <name> to the index. This will replace any previous
    entries for this model in the index.

  list [--model=<name>] [--limit=10] [--offset=0]

    List all entries in elasticsearch. If a model is specified only entries
    matching index and type of the model will be listed.

  delete --model=<name> [--id=<id>]

    Delete a document from an index. If no <id> is specified the whole
    index will be deleted.

  help

    Show this help

EOD;
    }

    /**
     * Index the given model in elasticsearch
     *
     * @param string $model name of the ActiveRecord class
     */
    public function actionIndex($model)
    {
        $n = 0;
        $name   = $model;
        $model  = CActiveRecord::model($name);
        $table  = $model->tableName();
        $count  = $model->count();
        $step   = $count > 5 ? floor($count/5) : 1;
        $index  = $model->elasticIndex;

        $this->message("Adding $count '$name' records from table '$table' to index '$index'\n 0% ", false);

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
     * List documents in elasticsearch
     *
     * @param string|null $model the optional model name
     * @param int $limit how many documents to show. Default is 10.
     * @param int $offset at which document to start. Default is 0.
     */
    public function actionList($model=null, $limit=10, $offset=0)
    {
        $search         = new Search;
        $search->size   = $limit;
        $search->from   = $offset;

        if($model!==null) {
            $name = $model;
            $model = CActiveRecord::model($name);
            $search->index  = $model->elasticIndex;
            $search->type   = $model->elasticType;
        }

        $search->query = array(
            'match_all' => array(),
        );
        $result = Yii::app()->elasticSearch->search($search);

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

        $request = Yii::app()->elasticSearch->client->delete($url);
        $request->send();

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
     * @param bool $newline wether to append a newline. Default is true.
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
}
