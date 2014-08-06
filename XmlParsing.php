<?php
/**
 * A reusable class for parsing xml, dealing with SimpleXmlElements, and transforming xml with xslt.
 *
 * @author Brad Busenius <bbusenius@uchicago.edu>
 */
class XmlParsing
{
     /**
     * Method converts PHP array, object, or string to xml to be appended to a SimpleXMLElement.
     * It can compensate for nested arrays and numeric keys.
     * Operates on a SimpleXMLElement.
     *
     * @param arr, string, or object to convert (flattens objects into arrays)
     * @param $xml a SimpleXMLElement to append to.
     *
     * @return an xml object.
     */
    public function array2xml($arr, &$xml) {
        foreach($arr as $key => $value) {
            if(is_array($value)) {
                if(!is_numeric($key)){
                    $subnode = $xml->addChild("$key");
                }
                else {
                    $subnode = $xml->addChild("value");
                    $subnode->addAttribute('key', $key);
                }
                $this->array2xml($value, $subnode);
            }
            elseif(is_object($value)) {
                $value = (array) $value;
                $subnode = $xml->addChild("$key");
                $this->array2xml($value, $subnode);
            }
            else {
                if (is_numeric($key)) {
                    $xml->addChild("value", $value)->addAttribute('key', $key);
                }
                else {
                    /* Fixes a problem where the second " is being improperly
                    encoded as $quot without a semicolon. How or why this is 
                    happening to the data is still unknown.*/
                    $value = htmlspecialchars($value, ENT_SUBSTITUTE);

                    $xml->addChild("$key", $value);
                }
            }
        }
    }

    /**
     * Method transforms xml with xsl.
     *
     * @param  $xml string
     * @param  $xsl string
     *
     * @return string xml
     */
    public function transform($xml, $xsl) {
        $xslt = new XSLTProcessor();
        $xslt->importStylesheet(new SimpleXMLElement($xsl));
        return $xslt->transformToXml(new SimpleXMLElement($xml));
    }

   /**
     * Helper method loops through an array or object and builds a new data structure
     * that only contains the first of repeated key/value pairs that are nested in the 
     * original data structure. Duplicate keys in the original data structure are ignored. 
     *
     * @param $data, the original array or object to filter.
     * @param optional $array, a target array to append to.
     *
     * @return a new array with data associated with the first of repeated keys.
     */
    public function getFirstRepeatedData($data, $array=array()) {
        foreach($data as $key => $value) {
            if (!isset($array[$key])) {
                $array[$key] = (string) $value;
            }
        }
        return $array;
    } 
}
?>
