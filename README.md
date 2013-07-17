# Warning - don't use this yet. I haven't even tested it at all.

# YiiElasticSearch

Elastic Search client for Yii.

# Installation

Install via composer, requires php >= 5.3

# Configuration

Add the following to your application config:

```php
'aliases' => array(
    'YiiElasticSearch' => 'application.vendor.phpnode.yiielasticsearch.src',
),
'components' => array(
    'elasticSearch' => array(
        'class' => 'YiiElasticSearch\Connection',
        'baseUrl' => 'http://localhost:9200/',
    ),
    ...
)
```

Also make sure, that you include the autoloader of composer. We recommend
to add this line to your `index.php` and maybe also your `yiic.php`:

```php
// Include composer autoloader
require_once(__DIR__.'/protected/vendor/autoload.php');
```

Make sure to modify the path so that it matches the location of your `vendor/`
directory.

# Usage

## Index your ActiveRecords

Attach the `YiiElasticSearch\SearchableBehavior` to any of your ActiveRecords to make it easy
to index and search your normal models with elasticsearch.

```php
class MyModel extends CActiveRecord
{
    public function behaviors()
    {
        return array(
            'searchable' => array(
                'class' => 'YiiElasticSearch\SearchableBehavior',
            ),
        );
    }
}
```

Now when MyModel instances are saved or deleted they will be automatically indexed or deleted in
elasticsearch as appropriate.


### Define an index for a record

By default your records will be stored in an index that uses your sanitized application name
(`Yii::app()->name`). To change it you can define

```php
class MyModel extends CActiveRecord
{
    public $elasticIndex = 'myindex';
```

or, if you need more control, create a method

```php
class MyModel extends CActiveRecord
{
    public function getElasticIndex()
    {
        return 'myindex';
    }
```


### Define a type for a record

By default the lower case class name will be used as type name in elasticsearch. If you want to
change that you can define

```php
class MyModel extends CActiveRecord
{
    public $elasticType = 'mymodel';
```

or, again, if you need more control, create a method

```php
class MyModel extends CActiveRecord
{
    public function getElasticType()
    {
        return 'mymodel';
    }
```

### Customize indexed data

By default all attributes are stored in the index. If you need to customize the data
that should be indexed in elasticsearch, you can override these two methods.

```php
class MyModel extends CActiveRecord
{
    /**
     * @param DocumentInterface $document the document where the indexable data must be applied to.
     */
    public function populateElasticDocument(DocumentInterface $document)
    {
        $document->setId($this->id);
        $document->name     = $this->name;
        $document->street   = $this->street;
    }

    /**
     * @param DocumentInterface $document the document that is providing the data for this record.
     */
    public function parseElasticDocument(DocumentInterface $document)
    {
        // You should always set the match score from the result document
        if ($document instanceof SearchResult)
            $this->setElasticScore($document->getScore());

        $this->id       = $document->getId();
        $this->name     = $document->name;
        $this->street   = $document->stree;
    }
```


## Query records

You can specify queries using the `YiiElasticSearch\Search` object. This object provides a simple
OO wrapper for the vanilla elasticsearch [search API](http://www.elasticsearch.org/guide/reference/api/search/).

For example:

```php
$search = new \YiiElasticSearch\Search("myindex", "mymodel");
$search->query = array(
    "match_all" => array()
);

// start returning results from the 20th onwards
$search->offset = 20;
```

With a search you can either perform a 'raw' query, e.g.

```php
$resultSet = Yii::app()->elasticSearch->search($search);
```

This will return a result set that is a very simple wrapper around the raw elastic search response.

Alternatively, when combined with a `SearchableBehavior` you can use data providers, e.g.

```php
$dataProvider = new \YiiElasticSearch\DataProvider(MyModel::model(), array(
        'search' => $search
));
```

The data from `$dataProvider->data` is a list of ActiveRecords, just like from an ordinary
`CActiveDataProvider`. So you can use it in any list or grid view.

## Raw requests

You can also use the connection component to send raw requests to elasticsearch.


```php
// Will be an instance of a Guzzle\Http\Client
$client = Yii::app()->elasticSearch->client;

$mapping = array(
   'country' => array(
        'properties' => array(
            'name' => array(
                'type' => 'string',
            ),
        ),
    ),

// Create a mapping
$request = $client->put('myindex', array("Content-type" => "application/json"));
$request->setBody(array('mapping' => $mapping));

$response = $request->send();

$result = $response->getBody();
```

## Console Maintenance

The extension comes with a simple maintenance command that can be helpful to find out
what's going on in your index. To configure it, add this to your `console.php` configuration:

```php
'commandMap' => array(
    'elastic' => array(
        'class' => 'YiiElasticSearch\ConsoleCommand',
    ),
),
```

This will allow you to use `yiic elastic` on the console. Here's the command help:

```
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
``````

