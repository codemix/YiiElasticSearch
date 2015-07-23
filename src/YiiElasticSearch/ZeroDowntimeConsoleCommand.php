<?php

namespace YiiElasticSearch;

/**
 * This is a zero downtime maintenance command for the elasticSearch component.
 *
 * More details: https://www.elastic.co/blog/changing-mapping-with-zero-downtime
 *
 * @author Peter Buri <peter.buri@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class ZeroDowntimeConsoleCommand extends \CConsoleCommand
{
    const DISPLAY_STEPS = 10;

    /**
     * @var array
     */
    protected $requestOptions = array(
        "Content-type" => "application/json"
    );

    public function getHelp()
    {

        return 'Usage: '.$this->getCommandRunner()->getScriptName().' '.$this->getName() . "\n" . <<<EOH

DESCRIPTION
  This is the maintenance command for the elasticsearch component.
  It provides utilities to manage entries in the elasticsearch index with zero downtime.

ACTIONS

  * index --models=<model1>,...
    Add all models to the index.

  * status --models=<model1>,...
    Displays actual indexes and aliases

  * schema --models=<model1>,...
        [--version=201512121212] [--forceMigrate=false] [--bulkCopy=true] [--updateAlias=true] [--deleteIndexes=true]

    Creates schema for the given models. Steps:
     1, compare mapping
     2, if migration is needed, create the new schema version, always create new if <forceMigrate> is true
     3, bulk copy the previous data if <bulkCopy> is true
     4, update aliases if <updateAlias> is true
     5, delete indexes if <deleteIndexes> is true


  * bulkCopy --from=index/type --to=index2/type [--properties=]
    Bulk copy all data

  * changeAlias --from=value --to=value [--old=]
    Change alias

  * deleteIndex --indexesToDelete=value
    Delete index with all types

EOH;
    }

    /**
     * @param $version
     * @return null
     * @throws \Exception
     */
    public function initVersion($version)
    {
        if ($version && !is_numeric($version)) {
            throw new \Exception('Version can be only numeric!');
        }
        if (!$version) {
            $version = date('YmdHis');
        }

        return $version;
    }

    /**
     * Indexes the given models
     *
     * @param $models
     * @param null $version
     */
    public function actionIndex($models, $version=null)
    {
        $models = explode(',', $models);

        if ($version) {
            SearchableBehavior::$indexPostfix = $version;
        }

        foreach($models as $model) {
            $this->indexModel($model);
        }

        if ($version) {
            SearchableBehavior::$indexPostfix = null;
        }
    }

    /**
     * Displays status of the given models
     *
     * @param $models
     */
    public function actionStatus($models)
    {
        /** @var \YiiElasticSearch\Connection $elastic */
        $elastic = \Yii::app()->elasticSearch;

        $status = $this->performRequest($elastic->client->get('/_status', $this->requestOptions));
        $aliases = $this->performRequest($elastic->client->get('/_alias', $this->requestOptions));

        $models = explode(',', $models);

        $indexes = array();
        foreach($models as $modelName) {
            $model = \CActiveRecord::model($modelName);

            if (!isset($indexes[$model->getElasticIndex()])) {
                $indexes[$model->getElasticIndex()] = array();
            }

            $indexes[$model->getElasticIndex()][] = $modelName;
        }

        $indexVersions = array();
        foreach($status['indices'] as $i=>$v) {
            if (preg_match('#(.*)'.SearchableBehavior::INDEX_SEPARATOR.'(\d+)#', $i, $matches)) {
                if (!isset($indexVersions[$matches[1]])) {
                    $indexVersions[$matches[1]] = array();
                }
                $indexVersions[$matches[1]][] = $matches[2];
            }
        }

        $aliasVersions = array();
        foreach($aliases as $i=>$v) {
            if (preg_match('#(.*)'.SearchableBehavior::INDEX_SEPARATOR.'(\d+)#', $i, $matches)) {
                if (!isset($indexVersions[$matches[1]])) {
                    $aliasVersions[$matches[1]] = array();
                }
                $aliasVersions[$matches[1]][] = $matches[2];
            }
        }

        echo "Status:\n\n";
        foreach($indexes as $index => $models) {
            echo "'", $index, "' index:\n\n";

            echo "  Models:\n";
            foreach($models as $model) {
                echo "    ", $model, "\n";
            }
            echo "\n";

            if (isset($indexVersions[$index])) {
                echo "  Versions:\n";
                foreach ($indexVersions[$index] as $version) {
                    echo "    ", $index, SearchableBehavior::INDEX_SEPARATOR, $version, "\n";
                }
                echo "\n";
            } else {
                echo "  No version found!\n";
            }

            if (isset($aliasVersions[$index])) {
                echo "  Pointing aliases:\n";
                foreach ($aliasVersions[$index] as $version) {
                    echo "    ", $index, SearchableBehavior::INDEX_SEPARATOR, $version, "\n";
                }
                echo "\n";
            } else {
                echo "  No alias found!\n";
            }

            echo "\n\n";
        }
    }

    /**
     * @param $models
     * @param null $version
     * @param bool|false $forceMigrate
     * @param bool|true $bulkCopy
     * @param bool|true $updateAlias
     * @param bool|true $deleteIndexes
     */
    public function actionSchema($models, $version=null, $forceMigrate=false, $bulkCopy=true,
        $updateAlias=true, $deleteIndexes=true)
    {
        $models = explode(',', $models);

        $version = $this->initVersion($version);

        /** @var \YiiElasticSearch\Connection $elastic */
        $elastic = \Yii::app()->elasticSearch;

        $indexesToDelete = array();

        $status = $this->performRequest($elastic->client->get('/_status', $this->requestOptions));
        $aliases = $this->performRequest($elastic->client->get('/_alias', $this->requestOptions));
        $mappings = $this->performRequest($elastic->client->get('/_mapping', $this->requestOptions));

        foreach($models as $modelName) {
            $model = \CActiveRecord::model($modelName);

            echo "{$modelName}:\n";

            $mainIndex = $model->getElasticIndex();
            $type = $model->getElasticType();

            $versionedIndex = $mainIndex. '_' . $version;

            // Dig out old index
            $currentIndex = null;
            foreach($aliases as $alias => $aliasDetail) {
                if (array_key_exists($mainIndex, $aliasDetail['aliases'])) {
                    $currentIndex = $alias;
                }
            }

            $mapping = $model->getElasticMapping();

            $migrate = $forceMigrate;
            if (!$migrate) {
                // Check mapping change
                if (isset($mappings[$currentIndex]['mappings'][$type])) {
                    $diff1 = $this->arrayRecursiveDiff($mappings[$currentIndex]['mappings'][$type], $mapping);
                    $diff2 = $this->arrayRecursiveDiff($mapping, $mappings[$currentIndex]['mappings'][$type]);

                    if ($diff1 || $diff2) {
                        echo "  changes: ", json_encode($diff1), " => ", json_encode($diff2), "\n";

                        $prompt = $this->prompt("  {$currentIndex}/{$type} changed, create new schema?", "No");
                        $migrate = substr(strtolower($prompt), 0, 1) == 'y' ? true : false;
                    } else {
                        echo "  OK!\n";
                    }
                } else {
                    $migrate = true;
                }
            }

            // Migrate if needed
            if ($migrate) {

                // Create index if needed
                if (isset($status['indices'][$versionedIndex])) {
                    echo "  Index: '{$versionedIndex}' exists.\n";
                } else {
                    $this->performRequest(
                        $elastic->client
                            ->put($versionedIndex, $this->requestOptions)
                            ->setBody('')
                    );
                    echo "  Index: '{$versionedIndex}' created.\n";

                    $status['indices'][$versionedIndex] = 'CREATED';
                };

                // Create mapping
                $this->performRequest(
                    $elastic->client
                        ->put($versionedIndex.'/_mapping/'.$type, $this->requestOptions)
                        ->setBody(json_encode(array(
                            "{$type}" => $mapping
                        )))
                );
                echo "  Type: '{$type}' created.\n";


                // Reindex if needed
                if ($bulkCopy && isset($mappings[$currentIndex]['mappings'][$type])) {
                    $properties = array_keys($mapping['properties']);

                    $this->actionBulkCopy(
                        $currentIndex . '/' . $type,
                        $versionedIndex . '/' .$type,
                        $properties
                    );
                } else {
                    echo "\n  WARNING: documents are not being copied!\n\n";
                }

                // Change alias
                if ($updateAlias) {
                    $this->actionChangeAlias($versionedIndex, $mainIndex, $currentIndex);
                }

                if ($currentIndex) {
                    $indexesToDelete[$currentIndex] = true;
                }

                $mappings[$versionedIndex]['mappings'][$type] = $mapping;
            }

            echo "\n";
        }

        if ($deleteIndexes && $deleteIndexes!=='false' && count($indexesToDelete)) {
            $this->actionDeleteIndex(array_keys($indexesToDelete));
        } elseif ($indexesToDelete) {
            echo "Not dropping the following indexes:\n";
            foreach(array_keys($indexesToDelete) as $index) {
                echo "  $index\n";
            }
        }

        echo "\n";
    }

    /**
     * @param $request
     * @see YiiElasticSearch\ConsoleCommand::performRequest
     * @return mixed
     */
    protected function performRequest($request)
    {
        try {
            $response = $request->send();
            return json_decode($response->getBody(true), true);
        } catch(\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            echo $e->getResponse()->getBody(true);

        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $body = $e->getResponse()->getBody(true);
            if(($msg = json_decode($body))!==null) {
                echo $msg->error;
            } else {
                echo $e->getMessage();
            }
        }

        echo "Error!\n";

        \Yii::app()->end(1);
    }

    private function arrayRecursiveDiff($aArray1, $aArray2) {
        $aReturn = array();

        foreach ($aArray1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                $aReturn[$mKey] = $mValue;
            }
        }

        return $aReturn;
    }

    public function actionBulkCopy($from, $to, $properties=null)
    {
        if (!$from) {
            throw new \InvalidArgumentException('The "from" parameter cannot be empty!');
        }
        if (!$to) {
            throw new \InvalidArgumentException('The "to" parameter cannot be empty!');
        }

        /** @var \YiiElasticSearch\Connection $elastic */
        $elastic = \Yii::app()->elasticSearch;

        echo '  Bulk copy:';
        $response = $this->performRequest(
            $elastic->client
                ->post($from . '/_search?search_type=scan&scroll=1m', $this->requestOptions)
                ->setBody(json_encode(array(
                    "fields" => array('_source', '_parent', '_routing', '_timestamp'),
                    "query" => array("match_all"=>array()),
                    "size" => 1000
                )))
        );
        $scroll_id = $response['_scroll_id'];

        //

        $total = $response['hits']['total'];
        $step  = $total > self::DISPLAY_STEPS ? floor($total/self::DISPLAY_STEPS) : 1;

        $count = 0;
        while($scroll_id) {
            $response = $this->performRequest(
                $elastic->client
                    ->post('/_search/scroll?scroll=1m', $this->requestOptions)
                    ->setBody($scroll_id)
            );
            $scroll_id = $response['_scroll_id'];

            $bulkData = "";
            if (isset($response['hits']['hits']) && $response['hits']['hits']) {
                foreach($response['hits']['hits'] as $hit) {

                    if (isset($hit['fields']['_parent'])) {
                        $bulkData .= json_encode(array("create"=>array("_id"=>$hit['_id'], "_parent"=>$hit['fields']['_parent']))). "\n";
                    } else {
                        $bulkData .= json_encode(array("create"=>array("_id"=>$hit['_id']))). "\n";
                    }

                    if ($properties) {
                        $bulkData .= json_encode(array_intersect_key($hit['_source'], array_flip($properties))). "\n";
                    } else {
                        $bulkData .= json_encode($hit['_source']). "\n";
                    }

                    $count++;
                }
            } else {
                $scroll_id = null;
            }

            // Bulk save
            if ($bulkData) {
                $this->performRequest(
                    $elastic->client
                        ->post($to . '/_bulk', $this->requestOptions)
                        ->setBody($bulkData)
                );
                unset($bulkData);
                $this->echoPercentage($count, $step);
            }
        };
        echo " 100% ({$count} document)\n";
    }

    public function actionChangeAlias($from, $to, $old=null)
    {
        /** @var \YiiElasticSearch\Connection $elastic */
        $elastic = \Yii::app()->elasticSearch;

        $data = array("actions"=>array());

        echo "  Change alias: $from -> $to";
        if ($old) {
            echo ", also delete: $old";
            $data["actions"][] = array("remove"=>array("alias"=>$to, "index"=>$old));
        }
        $data["actions"][] = array("add"=>array("alias"=>$to, "index"=>$from));

        echo "\n";

        $this->performRequest(
            $elastic->client
                ->post('/_aliases', $this->requestOptions)
                ->setBody(json_encode($data))
        );
    }

    public function actionDeleteIndex($indexesToDelete)
    {
        if (!is_array($indexesToDelete)) {
            $indexesToDelete = explode(',', $indexesToDelete);
        }

        /** @var \YiiElasticSearch\Connection $elastic */
        $elastic = \Yii::app()->elasticSearch;

        echo "Delete indexes:\n";
        foreach($indexesToDelete as $index) {
            echo "  $index\n";
            $this->performRequest(
                $elastic->client->delete('/'.$index, $this->requestOptions)
            );
        }
    }

    private function indexModel($modelName)
    {
        $model = \CActiveRecord::model($modelName);

        $count = 0;
        $total = $model->count();
        $step  = $total > self::DISPLAY_STEPS ? floor($total/self::DISPLAY_STEPS) : 1;

        echo "Indexing {$modelName}: ";

        $provider = new \CActiveDataProvider($model);
        $iterator = new \CDataProviderIterator($provider);
        foreach($iterator as $record) {
            $record->indexElasticDocument();
            $count++;
            $this->echoPercentage($count, $step);
        }

        echo "100%\n";

        return $count;
    }

    private function echoPercentage($count, $step)
    {
        if(($count % $step)===0) {
            $percent = (100/self::DISPLAY_STEPS)*$count/$step;
            if($percent < 100) {
                echo $percent.'% ';
            }
        }
    }
}