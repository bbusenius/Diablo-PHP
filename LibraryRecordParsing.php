<?php
/**
 * A reusable class for parsing library records such as MARC.
 * Contains methods related to parsing bibliographic and circulation data.
 *
 * @author Brad Busenius <bbusenius@uchicago.edu>
 */
class LibraryRecordParsing
{
     /**
     * Converts garbage ISBN/ISSN numbers into valid ISBN/ISSN numbers.
     *
     * @param $sequence, a single ISBN/ISSN as a string or an array of ISBN/ISSN numbers.
     *
     * @return an array of ISBN or ISSN numbers purged of impurities.
     */
    public function cleanIsxn($sequence) {
        $regEx = '/[^0-9a-zA-Z\-]/'; //will probably need tweaking
        $isxns = array();
        if(!empty($sequence)) {
            if (is_array($sequence)) {
                foreach ($sequence as $number) {
                    $data = preg_split($regEx, $number);
                    $isxns[] = $data[0];
                }
            }
            else {
                $data = preg_split($regEx, $sequence);
                $isxns[] = $data[0];
            }
        }
        else {
            $isxns = null;
        }
        return $isxns;
    }
}
?>
