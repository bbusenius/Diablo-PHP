<?php

/*Include query parsing library*/
include('QueryParsing.php');

/*Include xml parsing library*/
include('XmlParsing.php');

/*Include library specifc data related functions*/
include('LibraryRecordParsing.php');

/**
 * A class for sending a request to the SRU api for OLE.
 * Returns data from SRU to populate forms for various services
 * including Aeon, Scan and Deliver, and Can't Find It?
 *
 * @author Brad Busenius <bbusenius@uchicago.edu>
 */
class RecordDataFromSru extends QueryParsing
{
    /**
     * Allows users to set the url to the SRU api by passing a "database" parameter 
     * in their query. Defaults to production if no parameter is set.
     * 
     * @return a link to the SRU production database api or testing database api based
     * on the optional "database" parameter. No parameter set will return a link to 
     * the production database api. 
     */ 
    protected function getDatabase() {
        $data = $this->getQueryParameters(); 
        if (isset($data['database']) and $data['database'] == 'testing') {
            $sruAPI = 'http://raspberry.lib.uchicago.edu:8080/oledocstore/sru';
        }
        else {
            $sruAPI = 'http://blackberry.lib.uchicago.edu:8080/oledocstore/sru';
        }
        return $sruAPI;
    }

    /**
     * We use CURL to query the SRU api. This method allows us to set 
     * all the query parameters for the SRU api that we wish to pass.
     * These are the parameters added to the url set in getDatabase().
     * 
     * @return an array of key value pairs to use as query parameters 
     */ 
    protected function curlQueryParams() {
        return array(
            'operation' => 'searchRetrieve',
            'version' => '1.2',
            'query' => 'id%3d' . $this->getId(),
            'recordSchema' => 'opac',
            //'recordSchema' => 'marcxml',
        ); 
    }

    /**
     * Path to the Library of Congress 
     * MARCXML to MODS stylesheet. 
     * 
     * @return a string, the xsl stylesheet on disk
     */
    public function getXsl(){
        return file_get_contents('marc2mods.xsl');
    }
 
    /**
     * Gets the barcode from the incoming query to use with the SRU api 
     * if it was passed in thew query string as a parameter
     *
     * @return barcode if it exists
     */ 
    public function getBarcode() {
        return $this->getParameterValue('barcode');
    }

    /**
     * Gets the bibId from the incoming query to use with the SRU api 
     * if it was passed in thew query string as a parameter
     *
     * @return the bib id if it exists
     */ 
    protected function getId() {
        return $this->getParameterValue('bib');
    }

    /**
     * Gets the optional genre parameter from the incoming query.
     * Only used for requests sent to Aeon for Special Collections.
     *
     * @return the genre parameter as a string (monograph or manuscript).
     * If no genre is set, return false.
     */ 
    public function getGenre() {
        return $this->getParameterValue('genre'); 
    }

    /**
     * Gets the optional type parameter from the incoming query.
     * Only used for requests sent to request from DLL Storage for Law.
     *
     * @return the type parameter as a string (law or stor).
     * If no genre is set, return false.
     */ 
    protected function getDllRequestType() {
        return $this->getParameterValue('type');
    }

    /**
     * Gets the requested format from the incoming query. 
     * This should be php, json, or xml. It will be the format 
     * of the results we give in the end.
     * 
     * @return the format parameter as set in the query. If the original
     * query didsn't set a format, default to "php".
     */ 
    public function getRequestedFormat() {
        return $this->getParameterValue('format');
    } 

    /**
     * Method uses CURL to make a query to the SRU api
     * 
     * @return a string of xml about the record.
     */ 
    public function getRecordXml(){ 
        $ch = curl_init();
        //error_log($this->getDatabase() . $this->arrayToPost($this->curlQueryParams()));
        $curlConfig = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL            => $this->getDatabase() . $this->arrayToPost($this->curlQueryParams()), //GET is faster than POST
            CURLOPT_FOLLOWLOCATION =>  true
            //CURLOPT_URL            => $this->getDatabase(),
            //CURLOPT_POST           => true,
            //CURLOPT_POSTFIELDS     => $this->arrayToPost($this->curlQueryParams()),
        );
        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    } 

    /**
     * Helper method gets ISSNs or ISBNs from a SimpleXmlElement object created from marcxml 
     * returned from the SRU and transformed by the marcxml to mods stylesheet.
     * 
     * @param $type, string issn or isbn, the type of thing to look for.
     * @param $xmlObject the SimpleXmlElement object to act upon.
     * 
     * @return an array of ISSNs or ISSBNs (cleaned up by cleanIsxn).
     */
    public function getIsxn($type, $xmlObject) {
        $number = new LibraryRecordParsing(); 
        $isxn = array();
        foreach($xmlObject->identifier as $identifier){
            if (is_object($identifier) && $identifier->attributes() == $type) {
                $isxn[] = (string) $identifier;
            }
        }
        return $number->cleanIsxn($isxn);
    }
    
    /**
     * Method for crosswalking bib data to a more consistent data type.
     *
     * @param $bibData object, inconstently structured data object.
     *
     * @return a more consistently structured data object.
     */
    protected function cleanBibData($bibData) {
        $bibDataArray = array();
        foreach ($bibData->mods as $bibDataElement) {
            $bibDataArray[] = $bibDataElement;
        }
        return $bibDataArray[0];
    }

    /**
     * Method returns data about the place of publication from a SimpleXMLElement
     * that comes from transforming a marcxml bib record with the marc to mods 
     * xslt stylesheet.
     *
     * @param $type string, should match to a SimpleXMLElement attribute name.
     * Possible examples are "text" or "code".
     *
     * @param $xmlObject, a SimpleXMLElement from a marc to mods transformed record.
     *
     * @return string
     */ 
    public function getPlacePublishedInfo($type, $xmlObject) {
        $data = '';
        foreach($xmlObject->originInfo->place as $place){
            if($place->placeTerm->attributes() == $type) {
                $data = $data . $place->placeTerm; 
            }
        }
        return $data;
    }
 
    /**
     * Method gets title information from a SimpleXMLElement.
     *
     * @param $titleInfo, an array of SimpleXMLElements with title info.
     *
     * @return the full title.
     */
    protected function getTitle($titleInfo) {
        $filter = new XmlParsing();
        $titleInfoArray = array(); 
        if (is_array($titleInfo)) {
            foreach($titleInfo as $titleElements) {
                /*Takes the first title? - I'm not sure this is right*/
                $titleInfoArray = $filter->getFirstRepeatedData($titleElements, $titleInfoArray);
            }
        }
        else {
            $titleInfoArray = $filter->getFirstRepeatedData($titleInfo, $titleInfoArray);
        }
        return implode(' ', $titleInfoArray);
    }
 
    /**
     * Method returns bibliographic data or circulation data based on the parameters it's passed. 
     *
     * @param  $type can be a string "bib" or "circ" based on the desired data.
     * 
     * @return simplexml object representing the requested bibliographic or circulation data.
     */
    public function getRecordData() {
        
        /*Call the SRU once*/
        $sruXml = $this->getRecordXml();

       /*Nasty bugfix: for some reason raspberry returns marcxml without the collection element (not the case on blackberry).
        This causes the marcxml to mods xslt to fail for some unknown reason (though after looking at the schema it appears the
        collection element might be mandatory). Anyways, we rewrite the xml string to always have a collection element here. */ 
        if (!preg_match('/<collection/', $sruXml)) {
            $sruXml = preg_replace('/<record/', '<collection xmlns="http://www.loc.gov/MARC21/slim"><record', $sruXml);
            $sruXml = preg_replace('/<\/record>/', '</record></collection>', $sruXml);
        }

        /*Return the transformed xml as an object */
        $styledXml = new XmlParsing();
        $bibData = simplexml_load_string($styledXml->transform($sruXml, $this->getXsl()));

        /*Crosswalk $bibData to a more consistent data type.*/
        $bibData = $this->cleanBibData($bibData);
//print $bibData->asXml();
 
        /*Extract ISSNs and ISBNs for an easy add later*/
        $issns = $this->getIsxn('issn', $bibData[0]);
        $isbns = $this->getIsxn('isbn', $bibData[0]);
        
        /*Turn the untransformed sru xml into an object*/
        $xmlObject = simplexml_load_string($sruXml);
//print_r($xmlObject->xpath('//holdings'));

        /*Get the volume numbers and call#  prefixes for counting where 
        we are in relation to itemId in a different part of the array*/
        $circulations = $xmlObject->xpath('//circulation');
        $volumes = $xmlObject->xpath('//volume');
        $prefixes = $xmlObject->xpath('//shelvingData');

        /*Only process holdings/circulation data if it exists*/
        if (!empty($circulations)) {
            $i = 0;
            foreach($circulations as $circulation){
                $itemId = $circulation->itemId;
                if($itemId == $this->getBarcode()){
                    $itemKey = $i; 
                }
                $i++;
            }
            $volumeNumber = (string) $volumes[$itemKey]->enumeration;
            $prefix = (!empty($prefixes) ? (string) $prefixes[$itemKey] : '') ;

            /*Get the part of the XML that contains holdings and circulation data related to the 
            requested copy (based on barcode)*/ 
            $circData = array();
            $circData['circulation'] = $xmlObject->xpath('//circulation[itemId = "' . $this->getBarcode() . '"]');
            $circData['receiptAcqStatus'] = (string) $xmlObject->xpath('//circulation[itemId = "' . $this->getBarcode() 
                . '"]/ancestor::holding')[0]->receiptAcqStatus;
            $circData['localLocation'] = (string) $xmlObject->xpath('//circulation[itemId = "' . $this->getBarcode() 
                . '"]/ancestor::holding')[0]->localLocation;
            $circData['shelvingLocation'] = (string) $xmlObject->xpath('//circulation[itemId = "' . $this->getBarcode() 
                . '"]/ancestor::holding')[0]->shelvingLocation;
            $circData['callNumber'] = $xmlObject->xpath('//circulation[itemId = "' . $this->getBarcode() . '"]/ancestor::holding/callNumber');
            $circData['copyNumber'] = $xmlObject->xpath('//circulation[itemId = "' . $this->getBarcode() . '"]/ancestor::holding/copyNumber');
            $circData['volumeNumber'] = $volumeNumber;
            $circData['callNumberPrefix'] = $prefix;
          
            //print_r(array_merge($bibData, $circData));
            $data = (object) array_merge((array) $bibData, $circData, array('issn' => $issns), array('isbn' => $isbns));

        }
        else {
            $data = $bibData->mods;
        }
        return $data;
    }
 
    
    /**
     * Method returns an array of selected record data to populate 
     * service forms and other such things.
     *
     * @return bibliographic and circulation data as a 
     * php array, json, or xml (defaults to php).
     */
    public function getData() { 

        /*Get all record data from the SRU*/
        $recordData = $this->getRecordData();

        /*Populate an array with the data we want*/
        $data = array();
        $data['title'] = $this->getTitle($recordData->titleInfo);
        $data['location'] =  (isset($recordData->shelvingLocation) ? $recordData->shelvingLocation : null); 
        $data['callNumber'] = (isset($recordData->callNumber) ? implode($recordData->callNumber) : null);
        $data['callNumberPrefix'] = (isset($recordData->callNumberPrefix) ? $recordData->callNumberPrefix : null); 
        $data['copyNumber'] = (isset($recordData->copyNumber) ? implode($recordData->copyNumber) : null);
        $data['volumeNumber'] = (isset($recordData->volumeNumber) ? $recordData->volumeNumber : null);
        $data['author'] = (isset($recordData->name->namePart) ? (string) $recordData->name->namePart: null);
        $data['bibId'] = $this->getId();
        $data['barcode'] = $this->getBarcode();
        $data['publisher'] = (isset($recordData->originInfo->publisher) ? $recordData->originInfo->publisher->__toString() : null);
        $data['placePublished'] = (isset($recordData->originInfo->place->placeTerm) ? $this->getPlacePublishedInfo('text', $recordData) : null);
        $data['dateIssued'] = (isset($recordData->originInfo->dateIssued) ? trim($recordData->originInfo->dateIssued, '-') : null);
        $data['edition'] = (isset($recordData->originInfo->edition) ? (string) $recordData->originInfo->edition : null);
        $data['issn'] = (isset($recordData->issn) ? $recordData->issn : null);
        $data['isbn'] = (isset($recordData->isbn) ? $recordData->isbn : null);

        //$data = $this->getRecordXml();
        if ($this->getrequestedformat() == 'json') {
            $data = json_encode($data);
        }
        elseif ($this->getRequestedFormat() == 'xml') {
            /*SimpleXmlElement to act upon*/
            $xml = new SimpleXMLElement('<record/>');
            /*Register an object for parsing xml*/
            $xmlParse = new XmlParsing();
            $xmlParse->array2xml($data, $xml);
            $data = $xml->asXML();
        }
        return $data;
    }

    /**
     * Method for url encoding an array. Works on arrays with nested arrays and objects 1 level deep. 
     *
     * @return bibliographic and circulation data as a php array. 
     * Each item is url encoded. 
     */
    /*public function getEncodedData() {
        $data = $this->getData();
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
    }*/
}

/**
 * A class for special handling and processing of record data for form processing of conditional links.
 * Used in the forms for conditional service links.
 *
 * @author Brad Busenius <bbusenius@uchicago.edu>
 */
class ParseRecord extends RecordDataFromSru
{
    /**
     * Method for determining the type of search to send to the Relais service
     * used by BorrowDirect and Uborrow.
     *
     * @param $record, array of data about the record.
     *
     * @return the type of search to do as GET parameters. Search type will be ISBN, ISSN, or title/author. 
     * These are appended to the query thrown at Relais.
     */
    public function relaisSearchType($record) {
        /*ISBN*/
        if(!empty($record['isbn'])) {
            $search = 'isbn%3d' . $record['isbn'][0];
        }
        /*ISSN*/
        elseif(!empty($record['issn'])) {
            $search = 'issn%3d' .  $record['issn'][0];
        }
        /*Title and Author*/
        else {
            $search = 'ti%3D%22'.urlencode($record['title']).'%22 ' . (isset($record['author']) ? 'and au%3D%22' . urlencode($record['author']).'%22' : '' );
        }
        return $search; 
    }

    /**
     * Method for determining the type of search to send to the request from DLL service.
     * 
     * @return the path to a search form based on the kind of search being done.
     */
    public function dllSearchType() {

        if ($this->getDllRequestType() == 'law') {
            $path = 'DLL_storagerequest.php';
        }
        elseif ($this->getDllRequestType() == 'stor') {
            $path = 'paging.php';
        }
        else {
            $path = 'search-template.php';
        }
        return $path;
    }
}
?>
