<?php

/**
 * A reusable class for parsing and managing incoming queries to php pages.
 *
 * @author Brad Busenius <bbusenius@uchicago.edu>
 */
class QueryParsing
{
    public function __construct() {
        $this->queryString = $_SERVER['QUERY_STRING'];
    }

    /**
     * Get the incoming query string.
     */
    protected function getQueryString() {
        return $this->queryString;
    }

    /**
     * Get the parameters from the query string. And reformat them as
     * key / value pairs.
     *
     * @return an array of parameters as key value pairs
     */
    protected function getQueryParameters() {
        $parameters =  explode('&', $this->getQueryString());

        $data = array();
        foreach ($parameters as $p) {
            list($key, $value) = explode("=", $p);
            $data[$key] = urldecode($value);
        }
        return $data;
    }

    /**
     * Gets the given parameter value from the incoming query string.
     *
     * @param $param string, the parameter we are searching for in the GET string
     *
     * @return a string, the value of the passed parameter from the incoming GET string
     */
    public function getParameterValue($param) {
        $data = $this->getQueryParameters();
        return (isset($data[$param]) ? $data[$param]: false);
    }

    /**
     * Method assembles query parameters as a GET string or POST parameters.
     * Can be used to pass arguments to curl.
     *
     * @param associative array of query parameters as key value pairs
     *
     * @return a string of query parameters to pass to curl
     */
    public function arrayToPost($array) {
        $post = '';
        $i = 0;
        foreach ($array as $parameter => $value) {
            if ($i == 0) {
                $post .= '?' . $parameter . '=' . $value;
            }
            else {
                $post .= '&' . $parameter . '=' . $value;
            }
            $i ++;
        }

        return $post;
    }

    /**
     * Method for url encoding an array. Works on arrays with nested arrays and objects 1 level deep.
     *
     * @param $data, an array of data to urlencode. 
     *
     * @return bibliographic and circulation data as a php array.
     * Each item is url encoded.
     */
    public function getEncodedData($data) {

        /*Typecast in case an object is passed in*/
        $data = (array) $data;  
 
        $encodedData = array();
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                 foreach ($value as $v) {
                    $encodedData[$key][] = urlencode($v);
                 }
            }
            else {
                $encodedData[$key] = urlencode($value);
            }
        }
        return $encodedData;
    } 
}
?>
