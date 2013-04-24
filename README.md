# Warning - don't use this yet. I haven't even tested it at all.

# YiiElasticSearch

Elastic Search client for Yii.

# Installation

Install via composer, requires php >= 5.3

# Configuration

Add the following to your application config:

		'components' => array(
			'elasticSearch' => array(
				'class' => 'YiiElasticSearch\\Connection',
				'baseUrl' => 'http://localhost:9200/',
			),
			...
		)

# Usage

Extend from the `YiiElasticSearch\SearchableActiveRecord` class to make it easy to index and search your normal models with elastic search.
Make sure you specify the `indexName` and `typeName` properties, for example:

		class MyModel extends \YiiElasticSearch\SearchableActiveRecord
		{
				public $autoIndex = true; // it's true by default anyway
				protected $indexName = "main";
				protected $typeName = "mymodel";
		}

Now when MyModel instances are saved or deleted they will be automatically indexed or deleted in elastic search as appropriate.

You can specify queries using the `YiiElasticSearch\Criteria` object. This object provides a simple wrapper for the vanilla
elastic search query API. For example:

		$criteria = new \YiiElasticSearch\Criteria("main", "mymodel");
		$criteria->query = array(
				"match_all" => array()
		);
		$criteria->from = 20; // start returning results from the 20th onwards


With a criteria you can either perform a 'raw' query, e.g.

		$resultSet = Yii::app()->elasticSearch->search($criteria);

This will return a result set that is a very simple wrapper around the raw elastic search response.

Alternatively, when combined with a `SearchableActiveRecord` you can use data providers, e.g.

		$dataProvider = new \YiiElasticSearch\DataProvider(MyModel::model(), array(
				'criteria' => $criteria
		));

		CVarDumper::dumpAsString($dataProvider->getData());


