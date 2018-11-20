#!/usr/bin/php
<?php
    require __DIR__ . '/vendor/autoload.php';

    use Genkgo\Xsl\XsltProcessor;

    class DocxToJson {
        private $source_docx_filename;
        private $source_extracted_xml;
        private $xslt_transformation_filename;
        private $output_xml_filename;
        private $output_json_filename;

        static function extract_entry_from_zip(string $source_zip_filename, string $entry_name='word/document.xml', string $output_filename) {
            $content = '';
            $zip = zip_open($source_zip_filename);
            if (!$zip || is_numeric($zip)) {
                return false;
            }
            while ($zip_entry = zip_read($zip)) {
                if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
                if (zip_entry_name($zip_entry) != $entry_name) continue;
                $content .= zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                zip_entry_close($zip_entry);
                break;
            }
            zip_close($zip);
            return (file_put_contents($output_filename, $content)) === FALSE ? FALSE : $output_filename;
        }

        static function transform_with_xslt(string $from_xml_filename, string $by_xsl_style, string $to_filename) { 
            echo "from_xml_filename: $from_xml_filename;".PHP_EOL;
            echo "by_xsl_style: $by_xsl_style;".PHP_EOL;
            echo "to_filename: $to_filename;".PHP_EOL;
            $xsldoc = new DOMDocument();
            $xsldoc->load($by_xsl_style);

            $xmldoc = new DOMDocument();
            $xmldoc->load($from_xml_filename);

            $xsl = new XSLTProcessor();
            $xsl->importStyleSheet($xsldoc);
            $outputXmlData = $xsl->transformToXML($xmldoc);
            return (file_put_contents($to_filename, $outputXmlData)) === FALSE ? FALSE : $to_filename;
        }

        static function force_crate_parent_dir($full_file_name) {
            $output_dir = dirname($full_file_name);
            echo "dir: $output_dir" . PHP_EOL;
            return mkdir($output_dir, 0777, true);
        }

        public function __construct(string $source_docx_filename, $xslt_transformation_filename, string $output_json_filename) {
            $this->source_docx_filename = $source_docx_filename;
            $this->xslt_transformation_filename = $xslt_transformation_filename;
            $this->source_extracted_xml = null;
            $this->output_xml_filename = null; ///$source_docx_filename . '_tmp.xml';
            $this->output_json_filename = $output_json_filename;
        }

        private function extract_word_entry_from_docx() {
            if (!$this->source_extracted_xml) {
                $source_extracted_xml = $this->source_docx_filename . '_dir/' . 'source.xml';
                self::force_crate_parent_dir($source_extracted_xml);
                $this->source_extracted_xml = self::extract_entry_from_zip($this->source_docx_filename, 'word/document.xml', $source_extracted_xml);            
            }
            return $this->source_extracted_xml;
        }

        public function transform_to_xml($forced_source_xml, $output_xml_filename) {            
            if (!$output_xml_filename) {
                $output_xml_filename = './output/output.xml';
            }
            self::force_crate_parent_dir($output_xml_filename);
            if (!$forced_source_xml) {
                $forced_source_xml = $this->extract_word_entry_from_docx();
            }
            $this->output_xml_filename = self::transform_with_xslt($forced_source_xml, $this->xslt_transformation_filename, $output_xml_filename);
            return $this->output_xml_filename;
        }

        public function get_output_xml_filename() {
            return $this->output_xml_filename;
        }
    }

    $docxToJson = new DocxToJson('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl', ''/*TODO*/);
    $outputXml = $docxToJson->transform_to_xml(null, '/home/proxym/php-xslt-docx2json/output/calendar_2019_0.xml');
    echo $outputXml;

    ///$tmp_extracted_xml = '/home/proxym/php-xslt-docx2json/input/calendar_2019_0.xml';
    ///$tmp_extracted_xml = extract_entry_from_zip('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', 'word/document.xml', $tmp_extracted_xml);
    ///echo transform_with_xslt($tmp_extracted_xml, '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl', '/home/proxym/php-xslt-docx2json/output/calendar_2019_0.xml');
    echo PHP_EOL;
?>
