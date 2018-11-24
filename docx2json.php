#!/usr/bin/php
<?php
    require __DIR__ . '/vendor/autoload.php';

    use Genkgo\Xsl\XsltProcessor;

    function startsWith($haystack, $needle): bool { // search backwards starting from haystack length characters from the end
        return $needle === ''
          || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }
    
    function endsWith($haystack, $needle): bool { // search forward starting from end minus needle length characters
        if ($needle === '') {
            return true;
        }
        $diff = \strlen($haystack) - \strlen($needle);
        return $diff >= 0 && strpos($haystack, $needle, $diff) !== false;
    }

    function force_create_parent_dir(string $full_file_name) {
        $output_dir = dirname($full_file_name);
        //echo "dir: $output_dir" . PHP_EOL;
        if (is_dir($output_dir)) {
            return TRUE;
        } else {
            return mkdir($output_dir, 0777, true);
        }
    }

    function get_calendar_year(string $source_calendar_filename) {
        $filename = basename($source_calendar_filename);
        $matches = [];
        if (preg_match("/[1-9][0-9][0-9][0-9]/", $filename, $matches)) {
            return $matches[0];
        }
        return "0000";
    }

    function get_file_by_url(string $url) {
        if (startsWith($url, '/') || startsWith($url, '.')) {
            return file_get_contents($url);
        } else {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            $data = curl_exec($curl);
            curl_close($curl);
            return $data;
        }
    }

    class XmlToXml {
        protected $xslt_transformation_to_xml;
        protected $source_xml_filenames;
        protected $output_xml_filename;
        protected $calendar_year;

        public function __construct(string $calendar_year, array $source_xml_filenames, string $xslt_transformation_to_xml, string $output_xml_filename) {
            $this->calendar_year = $calendar_year;
            $this->source_xml_filenames = $source_xml_filenames;
            $this->output_xml_filename = $output_xml_filename;
            $this->xslt_transformation_to_xml = $xslt_transformation_to_xml;
        }

        /**
         * $from_xml_filename[0] must be root xml document.
         */
        protected final function transform_with_xslt(array $from_xml_filename, string $by_xsl_style, $source_docx_filename) { 
            if (!$from_xml_filename || count($from_xml_filename)==0) {
                return FALSE;
            }
            echo "source_xml_filename: $from_xml_filename[0];".PHP_EOL;
            echo "by_xsl_style: $by_xsl_style;".PHP_EOL;
            ///echo "to_filename: $to_filename;".PHP_EOL;
            $xsldoc = new DOMDocument();
            $xsldoc->load($by_xsl_style);

            $xmldoc = new DOMDocument();
            $xmldoc->load($from_xml_filename[0]); // e.g.: "/word/document.xml"

            for ($i=1; $i<count($from_xml_filename); ++$i) {
                echo "source_xml_child: $from_xml_filename[$i];".PHP_EOL;
                $child_xmldoc = new DOMDocument();
                $child_xmldoc->load($from_xml_filename[$i]); // e.g.: "/word/_rels/document.xml.rels"
                $xmldoc->documentElement->appendChild($xmldoc->importNode($child_xmldoc->documentElement, true));
            }

            $xsl = new \XSLTProcessor();
            $xsl->importStyleSheet($xsldoc);
            $xsl->setParameter("", "sourceXmlFileName", $from_xml_filename[0]);
            if ($this->calendar_year) {
                $xsl->setParameter("", "sourceCalendarYear", $this->calendar_year);
            }
            if ($source_docx_filename) {
                $xsl->setParameter("", "sourceDocxFileName", $source_docx_filename);
            }
            $outputXmlData = $xsl->transformToXml($xmldoc);
            return $outputXmlData;
        }

        protected function beautifyOutputXmlData(string $outputXmlData): string {
            $formattingTags = array('f:bold', 'f:italic', 'f:strikethrough', 'ins', 'del','f:line', 'span');
            foreach ($formattingTags as $tag) {
				// Convert parallel repeated tags to single instance
				// e.g. `<i:x>foo</i:x>  <i:x>bar</i:x>` to `<i:x>foo bar</i:x>`
				$outputXmlData = preg_replace("/(<\/{$tag}>)[ ]+<{$tag}>/", ' ', $outputXmlData);
				// e.g. `<i:x>foo_</i:x><i:x>bar</i:x>` to `<i:x>foo_bar</i:x>`
				$outputXmlData = preg_replace("/(<\/{$tag}>)<{$tag}>/", '', $outputXmlData);

				// Remove any number of spaces that follow the opening tag
				$outputXmlData = preg_replace("/(<{$tag}[^>]*>)[ ]*/", ' \\1', $outputXmlData);

				// Remove multiple spaces before closing tags
				$outputXmlData = preg_replace("/[ ]*<\/{$tag}>/", "</{$tag}>", $outputXmlData);
			}
            
            // Remove leading whitespace before closing tags
            $outputXmlData = preg_replace('/\s*(\<\/)/m', '$1', $outputXmlData);

            // Remove whitespace between tags
            $outputXmlData = preg_replace('/(\>)\s*(\<)/m', '$1$2', $outputXmlData);

            $outputXmlData = preg_replace('/&lt;/', '<', $outputXmlData);
            $outputXmlData = preg_replace('/&gt;/', '>', $outputXmlData);

            return $outputXmlData;
        }

        protected function remove_empty_tags_from_dom_document($doc) { // Remove empty tags
			$xpath = new DOMXPath($doc);
			while (($nodes = $xpath->query('//*[not(*) and not(\'i:image\') and not(text()[normalize-space()])]')) && ($nodes->length)) {
				foreach ($nodes as $node) {
					$node->parentNode->removeChild($node);
				}
            }
            return $doc;
        }

        protected function transform_with_xslt_to_xml(array $from_xml_filename, string $by_xsl_style, string $to_filename, $source_docx_filename) { 
            $outputXmlData = $this->transform_with_xslt($from_xml_filename, $by_xsl_style, $source_docx_filename);
            if (!$outputXmlData) {
                return FALSE;
            }

            $outputXmlData = $this->beautifyOutputXmlData($outputXmlData);
            $result = new DOMDocument("1.0");
            $result->preserveWhiteSpace = false;
            $result->formatOutput = true;
            $result->loadXML($outputXmlData);

            $this->remove_empty_tags_from_dom_document($result);

            $result->encoding = "UTF-8";
            $good = $result->save($to_filename);
            return ($good === FALSE) ? FALSE : $to_filename;
        }

        protected function transform_to_xml(array $forced_source_xml, string $output_xml_filename) {            
            force_create_parent_dir($output_xml_filename);
            $this->output_xml_filename = $this->transform_with_xslt_to_xml($forced_source_xml, $this->xslt_transformation_1_to_xml, $output_xml_filename, $this->source_docx_filename);
            return $this->output_xml_filename;
        }

        public function transform_to_xml_0(array $forced_source_xml, $output_xml_filename) {
            if (count($forced_source_xml) == 0) {
                return FALSE;
            }
            if (!$output_xml_filename) {
                $output_xml_filename = './output/' . basename($forced_source_xml[0]) . "_out.xml"; ///'./output/output.xml';
            }
            return $this->transform_to_xml($forced_source_xml, $output_xml_filename);
        }

        public function get_xslt_transformation() {
            return $this->xslt_transformation_to_xml;
        }

        public function get_source_xml_filenames() {
            return $this->source_xml_filenames;
        }

        public function get_output_xml_filename() {
            return $this->output_xml_filename;
        }     

        public function get_calendar_year() {
            return $this->calendar_year;
        }
    }

    class DocxToXml extends XmlToXml {
        protected $source_docx_filename;
        protected $source_extracted_xml;
        protected $xslt_transformation_1_to_xml;
        protected $output_xml_filename_1;

        public function get_source_docx_filename() {
            return $this->source_docx_filename;
        }

        public function get_source_extracted_xml() {
            return $this->source_extracted_xml;
        }

        public function get_xslt_transformation() {
            return $this->xslt_transformation_1_to_xml;
        }

        public function get_output_xml_filename_1() {
            return $this->output_xml_filename_1;
        }
      
        /**
         * $entry_names[0] must be root xml document entry.
         */
        protected final function extract_entries_from_zip(string $source_zip_filename, array $entry_names, string $output_dirname) {            
            $content = "";
            $output_dirname .= (endsWith($output_dirname, "/")? "" : "/");
            $main_entry_name = (count($entry_names)>0) ? $entry_names[0] : null;
            $zip = zip_open($source_zip_filename);
            if (!$zip || is_numeric($zip)) {
                return false;
            }
            $result = [];
            $good = true;
            while ($zip_entry = zip_read($zip)) {
                if (zip_entry_open($zip, $zip_entry) == FALSE) continue;
                $zip_entry_name = zip_entry_name($zip_entry);
                $entry_names_count = count($entry_names);
                if ($entry_names_count>0) {
                    $matched = array_filter($entry_names, function($e) use ($zip_entry_name) {
                        if ($e === $zip_entry_name) {
                            return true;
                        } else if (endsWith($e, "/")) {
                            return startsWith($zip_entry_name, $e);
                        } else {
                            return false;
                        }
                    });
                    if (count($matched) == 0) continue;
                }
                $content = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                $output_filename = $output_dirname . $zip_entry_name;
                //echo "output_filename: $output_filename" . PHP_EOL;
                if (!$main_entry_name) {
                    $main_entry_name = $zip_entry_name;
                }
                if ($main_entry_name === $zip_entry_name) {
                    array_unshift($result, $output_filename);
                } else {
                    if (in_array($zip_entry_name, $entry_names)) { // add to $result documents only, but not folders with resources.
                        array_push($result, $output_filename);
                    }
                }
                force_create_parent_dir($output_filename);
                $good = file_put_contents($output_filename, $content)!==FALSE && $good;
                zip_entry_close($zip_entry);
            }
            zip_close($zip);
            return $good === FALSE ? FALSE : $result;
        }

        protected function get_local_docx_path(string $source_document_url) {
            $source_docx_data = get_file_by_url($source_document_url);
            if (!$source_docx_data) {
                return FALSE;
            }
            $source_dir = "./input";
            $source_document_tmp = $source_dir . "/" . basename($source_document_url);
            force_create_parent_dir($source_document_tmp);
            $good = file_put_contents($source_document_tmp, $source_docx_data)!==FALSE;
            if (!$good) {
                return FALSE;
            }
            $ext = pathinfo($source_document_tmp, PATHINFO_EXTENSION);
            if ($ext != "docx") {
                echo "NOT docx file on input `{$source_document_tmp}`, so you must convert it to docx first." . PHP_EOL;
                echo "Firstly, install (`apt install libreoffice` or `yum/rpm install libreoffice`) or find (`which libreoffice`) libreoffice (usually it is /usr/bin/libreoffice )." . PHP_EOL;
                $sourceFile = $source_document_tmp;
                $outputDirectory = dirname($sourceFile);
                $source_docx_tmp = $outputDirectory . '/' . pathinfo($source_document_tmp, PATHINFO_FILENAME) . ".docx";
                echo "Secondary, convert your `{$source_document_tmp}` to `{$source_docx_tmp}` with like this: " . PHP_EOL . " libreoffice --headless --convert-to docx {$sourceFile} --outdir {$outputDirectory}" . PHP_EOL; ///
                echo "Thirdly, rerun the program with `docx` file (`{$source_docx_tmp}`) on input." . PHP_EOL;
                $source_docx_tmp = FALSE; // auto covertion was removed.
                exit(2);
            } else {
                $source_docx_tmp = $source_document_tmp;
            }
            return $source_docx_tmp;
        }

        protected function extract_word_entry_from_docx() {
            if (!$this->source_extracted_xml) {
                $source_docx_tmp = $this->get_local_docx_path($this->source_docx_filename);
                $source_extracted_xml_dir = $source_docx_tmp . "_dir";
                force_create_parent_dir($source_extracted_xml_dir);
                $entry_names = ["word/document.xml", "word/_rels/document.xml.rels", "word/media/", "word/footer1.xml", "word/footnotes.xml", "word/styles.xml", "docProps/app.xml", "docProps/core.xml"];
                $this->source_extracted_xml = $this->extract_entries_from_zip($source_docx_tmp, $entry_names, $source_extracted_xml_dir);            
            }
            return $this->source_extracted_xml;
        }

        public function __construct(string $calendar_year, string $source_docx_filename, string $xslt_transformation_1_to_xml, string $output_xml_filename_1/*, $xslt_transformation_2_to_xml , $xslt_transformation_3_to_json, string $output_json_filename*/) {
            $this->source_extracted_xml = null;
            $this->source_docx_filename = $source_docx_filename;
            $forced_source_xmls = $this->extract_word_entry_from_docx();
            if (!$forced_source_xmls) {
                return FALSE;
            }
            
            parent::__construct($calendar_year, $forced_source_xmls, $xslt_transformation_1_to_xml, $output_xml_filename_1);

            $this->xslt_transformation_1_to_xml = $xslt_transformation_1_to_xml;
            
            $this->output_xml_filename_1 = $output_xml_filename_1; ///$source_docx_filename . '_tmp.xml';
        }

        public function transform_to_xml_1() {
            if (count($this->source_extracted_xml) == 0) {
                return FALSE;
            }
            $output_xml_filename = $this->output_xml_filename_1;
            return $this->transform_to_xml($this->source_extracted_xml, $output_xml_filename);
        }
    }

    class DocxToXmlScripture extends DocxToXml {
        protected $xslt_transformation_2_to_xml;
        protected $output_xml_scripture_filename;

        public function get_xslt_transformation() {
            return $this->xslt_transformation_2_to_xml;
        }

        public function get_xml_scripture_filename() {
            return $this->xml_scripture_filename;
        }

        public function __construct(string $calendar_year, string $source_docx_filename, string $xslt_transformation_2_to_xml, string $output_xml_scripture_filename/*, $xslt_transformation_2_to_xml , $xslt_transformation_3_to_json, string $output_json_filename*/) {
            $dir = dirname($output_xml_scripture_filename);
            $xslt_transformation_1_to_xml = './xslt/wordtoxml_xslt1.xsl';
            $this->output_xml_filename_1 = $dir . '/' . 'out1.xml';
            parent::__construct($calendar_year, $source_docx_filename, $xslt_transformation_1_to_xml, $this->output_xml_filename_1);

            $this->output_xml_filename_1 = $this->transform_to_xml_1();
            if (!$this->output_xml_filename_1) {
                return FALSE;
            }

            $this->xslt_transformation_2_to_xml = $xslt_transformation_2_to_xml;            
            $this->output_xml_scripture_filename = $output_xml_scripture_filename;
        }

        protected function transform_xml_to_xml_scripture(array $output_xml_filename, string $output_xml_scripture_filename) {
            force_create_parent_dir($output_xml_scripture_filename);
            return $this->transform_with_xslt_to_xml($output_xml_filename, $this->xslt_transformation_2_to_xml, $output_xml_scripture_filename, $this->source_docx_filename);
        }

        public function transform_to_xml_scripture() {
            if (!$this->output_xml_scripture_filename) {
                return FALSE;
            }
            return $this->transform_xml_to_xml_scripture([$this->output_xml_filename_1], $this->output_xml_scripture_filename);
        }
    }

    class XmlToJson extends XmlToXml {
        protected $xslt_transformation_to_json;
        protected $source_xml_filenames;
        protected $output_json_filename;

        public function __construct(string $calendar_year, array $source_xml_filenames, string $xslt_transformation_to_json, string $output_json_filename) {
            $this->calendar_year = $calendar_year;
            $this->source_xml_filenames = $source_xml_filenames;
            $this->output_json_filename = $output_json_filename;
            $this->xslt_transformation_to_json = $xslt_transformation_to_json;
        }

        protected function transform_with_xslt_to_json(array $from_xml_filename, string $by_xsl_style, string $to_filename, $source_docx_filename) { 
            $outputXmlData = $this->transform_with_xslt($from_xml_filename, $by_xsl_style, $source_docx_filename);
            if (!$outputXmlData) {
                return FALSE;
            }

            $good = file_put_contents($to_filename, $outputXmlData)!==FALSE;
            return ($good === FALSE) ? FALSE : $to_filename;
        }

        protected function transform_xml_to_json(array $source_xml_filenames, string $output_json_filename, $source_docx_filename) {
            force_create_parent_dir($output_json_filename);
            return $this->transform_with_xslt_to_json($source_xml_filenames, $this->xslt_transformation_to_json, $output_json_filename, $source_docx_filename);
        }

        public function transform_to_json($optional_source_docx_filename) {
            $source_docx_filename = $optional_source_docx_filename;
            return $this->transform_xml_to_json($this->source_xml_filenames, $this->output_json_filename, $source_docx_filename);
        }

        public function get_source_xml_filenames() {
            return $this->source_xml_filenames;
        }

        public function get_output_json_filename() {
            return $this->output_json_filename;
        }

        public function get_xslt_transformation_to_json() {
            return $this->xslt_transformation_to_json;
        }
    }

    class DocxToJsonScripture extends DocxToXmlScripture {
        protected $xmlToJson;
        protected $outputXmlScripture;
        
        public function __construct(string $calendar_year, string $source_docx_filename, string $output_json_filename) {
            $this->calendar_year = $calendar_year;
            $xslt_transformation_2_to_xml = './xslt/xmltoxml_scripture.xsl';
            $output_xml_scripture_filename = './output/calendar_2019_0_scripture.xml';
            parent::__construct($calendar_year, $source_docx_filename, $xslt_transformation_2_to_xml, $output_xml_scripture_filename);
            $this->output_json_filename = $output_json_filename;
            $this->outputXmlScripture = $this->transform_to_xml_scripture();
            if (!$this->outputXmlScripture) {
                return FALSE;
            }
            $xslt_transformation_3_to_json = './xslt/xml_scripture_to_json.xsl';
            $this->xmlToJson = new XmlToJson($calendar_year, [$this->outputXmlScripture], $xslt_transformation_3_to_json, $output_json_filename);
        }

        public function transform_to_json_scripture() {
            $source_docx_filename = $this->get_source_docx_filename();
            return $this->xmlToJson->transform_to_json($source_docx_filename);
        }

        public function get_object_xml_to_json() {
            return $this->xmlToJson;
        }

        public function get_object_docx_to_xml_scripture() {
            return $this;
        }
    }

    $source_calendar_filename = './input/calendar_2019_0.docx';
    ///$source_calendar_filename = 'https://www.rop.ru/d/3000/d/calendar_2019_0.doc';
    $calendar_year = get_calendar_year($source_calendar_filename);

    echo 'calendar_year from filename: ' . $calendar_year . PHP_EOL;

    // $docxToxml = new DocxToXml($calendar_year, './input/calendar_2019_0.docx', './xslt/wordtoxml_xslt1.xsl', './output/calendar_2019_0.xml'/*TODO*/);
    // $outputXml = $docxToxml->transform_to_xml_1();
    // if ($outputXml === FALSE) {
    //     echo "ERROR 1" . PHP_EOL;
    //     exit(1);
    // }

    // $docxToXmlScripture = new DocxToXmlScripture($calendar_year, './input/calendar_2019_0.docx', './xslt/xmltoxml_scripture.xsl', './output/calendar_2019_0_scripture.xml'/*TODO*/);  
    // $outputXmlScripture = $docxToXmlScripture->transform_to_xml_scripture();
    // if ($outputXmlScripture === FALSE) {
    //     echo "ERROR 2" . PHP_EOL;
    //     exit(1);
    // }

    $docxToJsonScripture = new DocxToJsonScripture($calendar_year, $source_calendar_filename, './output/calendar_2019.json', $calendar_year);  
    $outputJson = $docxToJsonScripture->transform_to_json_scripture();
    if ($outputJson === FALSE) {
        echo "ERROR 3" . PHP_EOL;
        exit(1);
    }


    echo PHP_EOL;
?>
