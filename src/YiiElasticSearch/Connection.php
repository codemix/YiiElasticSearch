<?php

namespace YiiElasticSearch;

use \CApplicationComponent as ApplicationComponent;

use \Yii as Yii;


/**
 * The elastic search connection is responsible for actually interacting with elastic search,
 * e.g. indexing documents, performing queries etc.
 *
 * It should be configured in the application config under the components array
 * <pre>
 *  'components' => array(
 *      'elasticSearch' => array(
 *          'class' => "YiiElasticSearch\\Connection",
 *          'baseUrl' => 'http://localhost:9200/',
 *      )
 *  ),
 * </pre>
 *
 * @author Charles Pick <charles.pick@gmail.com>
 * @licence MIT
 * @package YiiElasticSearch
 */
class Connection extends ApplicationComponent
{
    /**
     * @var string the base URL that elastic search is available from
     */
    public $baseUrl = "http://localhost:9200/";

    /**
     * @var boolean whether or not to profile elastic search requests
     */
    public $enableProfiling = false;

    /**
     * @var string an optional prefix for the index. Default is ''.
     */
    public $indexPrefix = '';

    /**
     * @var \Guzzle\Http\Client the guzzle client
     */
    protected $_client;

    /**
     * @var \Guzzle\Http\Client the async guzzle client
     */
    protected $_asyncClient;

    /**
     * @param \Guzzle\Http\Client $asyncClient
     */
    public function setAsyncClient($asyncClient)
    {
        $this->_asyncClient = $asyncClient;
    }

    /**
     * @return \Guzzle\Http\Client
     */
    public function getAsyncClient()
    {
        if ($this->_asyncClient === null) {
            $this->_asyncClient = new \Guzzle\Http\Client($this->baseUrl);
            $this->_asyncClient->addSubscriber(new \Guzzle\Plugin\Async\AsyncPlugin());
        }
        return $this->_asyncClient;
    }

    /**
     * @param \Guzzle\Http\Client $client
     */
    public function setClient($client)
    {
        $this->_client = $client;
    }

    /**
     * @return \Guzzle\Http\Client
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $this->_client = new \Guzzle\Http\Client($this->baseUrl);
        }
        return $this->_client;
    }

    /**
     * Add a document to the index
     * @param DocumentInterface $document the document to index
     * @param bool $async whether or not to perform an async request.
     *
     * @return \Guzzle\Http\Message\Response|mixed the response from elastic search
     */
    public function index(DocumentInterface $document, $async = false)
    {
        $url = $document->getIndex().'/'.$document->getType().'/'.$document->getId();
        $client = $async ? $this->getAsyncClient() : $this->getClient();
        $request = $client->put($url)->setBody(json_encode($document->getSource()));
        return $this->perform($request, $async);
    }

    /**
     * Remove a document from elastic search
     * @param DocumentInterface $document the document to remove
     * @param bool $async whether or not to perform an async request
     *
     * @return \Guzzle\Http\Message\Response|mixed the response from elastic search
     */
    public function delete(DocumentInterface $document, $async = false)
    {
        $url = $document->getIndex().'/'.$document->getType().'/'.$document->getId();
        $client = $async ? $this->getAsyncClient() : $this->getClient();
        $request = $client->delete($url);
        return $this->perform($request, $async);
    }

    /**
     * Perform an elastic search
     * @param Search $search the search parameters
     *
     * @return ResultSet the result set containing the response from elastic search
     */
    public function search(Search $search)
    {
        $query = json_encode($search->toArray());
        $url = array();
        if ($search->index)
            $url[] = $this->indexPrefix.$search->index;
        if ($search->type)
            $url[] = $search->type;

        $url[] = "_search";
        $url = implode("/",$url);

        $client = $this->getClient();
        $request = $client->post($url, null, $query);
        $response = $this->perform($request);
        return new ResultSet($search, $response);
    }


    /**
     * Perform a http request and return the response
     *
     * @param \Guzzle\Http\Message\RequestInterface $request the request to preform
     * @param bool $async whether or not to perform an async request
     *
     * @return \Guzzle\Http\Message\Response|mixed the response from elastic search
     * @throws \Exception
     */
    public function perform(\Guzzle\Http\Message\RequestInterface $request, $async = false)
    {
        try {
            $profileKey = null;
            if ($this->enableProfiling) {
                $profileKey = __METHOD__.'('.$request->getUrl().')';
                if ($request instanceof \Guzzle\Http\Message\EntityEnclosingRequest)
                    $profileKey .= " ".$request->getBody();
                Yii::beginProfile($profileKey);
            }
            $response = $async ? $request->send() : json_decode($request->send()->getBody(true), true);
            Yii::trace("Sent request to '{$request->getUrl()}'", 'application.elastic.connection');
            if ($this->enableProfiling)
                Yii::endProfile($profileKey);
            return $response;
        }
        catch (\Guzzle\Http\Exception\BadResponseException $e) {
            $body = $e->getResponse()->getBody(true);
            if(($msg = json_decode($body))!==null) {
                throw new \CException($msg->error);
            } else {
                throw new \CException($e);
            }
        }
        catch(\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            throw new \CException($e->getResponse()->getBody(true));
        }
    }

    /**
     * @param string $url of resource to check e.g. /twitter/tweet
     * @return bool whether there are documents for this type
     */
    public function typeEmpty($url)
    {
        $url = '/'.trim($url,'/').'/_count';
        try {
            $response = $this->getClient()->get($url)->send()->json();
            return !isset($response['count']) || !$response['count'];
        }
        catch (\Guzzle\Http\Exception\BadResponseException $e) { }
        catch(\Guzzle\Http\Exception\ClientErrorResponseException $e) { }

        return false;
    }

    /**
     * @param string $url the resource URL to check e.g. /twitter or /twitter/tweet
     * @return bool whether a mapping exists for the given resource
     */
    public function mappingExists($url)
    {
        $url = '/'.trim($url,'/').'/_mapping';
        try {
            $response = $this->getClient()->get($url)->send();
            return true;
        }
        catch (\Guzzle\Http\Exception\BadResponseException $e) { }
        catch(\Guzzle\Http\Exception\ClientErrorResponseException $e) { }

        return false;
    }

    /**
     * Escapes the following terms:
     * + - && || ! ( ) { } [ ] ^ " ~ * ? : \
     *
     * @param $term
     * @return string
     * @link http://lucene.apache.org/core/3_4_0/queryparsersyntax.html#Escaping%20Special%20Characters
     */
    public function escape($term)
    {
        $result = $term;
        // \ escaping has to be first, otherwise escaped later once again
        $chars = array('\\', '+', '/', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':');

        foreach ($chars as $char) {
            $result = str_replace($char, '\\' . $char, $result);
        }
        return trim($result);
    }
}
