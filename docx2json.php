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

    function force_crate_parent_dir(string $full_file_name) {
        $output_dir = dirname($full_file_name);
        echo "dir: $output_dir" . PHP_EOL;
        return mkdir($output_dir, 0777, true);
    }

    class XmlToXml {
        protected $xslt_transformation_to_xml;
        protected $source_xml_filenames;
        protected $output_xml_filename;

        public function __construct(array $source_xml_filenames, string $xslt_transformation_to_xml, string $output_xml_filename) {
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

            $xsl = new XSLTProcessor();
            $xsl->importStyleSheet($xsldoc);
            $xsl->setParameter("", "sourceXmlFileName", $from_xml_filename[0]);
            if ($source_docx_filename) {
                $xsl->setParameter("", "sourceDocxFileName", $source_docx_filename);
            }
            $outputXmlData = $xsl->transformToXml($xmldoc);
            return $outputXmlData;
        }

        protected function beautifyOutputXmlData(string $outputXmlData): string {
            $formattingTags = array('f:bold', 'f:italic', 'f:strikethrough', 'f:line');
            foreach ($formattingTags as $tag) {
				// Convert parallel repeated tags to single instance
				// e.g. `<i:x>foo</i:x><i:x>bar</i:x>` to `<i:x>foo bar</i:x>`
				$outputXmlData = preg_replace("/(<\/{$tag}>)[ ]*<{$tag}>/", ' ', $outputXmlData);

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
            force_crate_parent_dir($output_xml_filename);
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

        public function get_output_xml_filename() {
            return $this->output_xml_filename;
        }

        public function get_output_json_filename() {
            return $this->output_json_filename;
        }
    }

    class DocxToXml extends XmlToXml {
        protected $source_docx_filename;
        protected $source_extracted_xml;
        protected $xslt_transformation_1_to_xml;
        protected $xslt_transformation_2_to_xml;
        protected $xslt_transformation_3_to_json;
        protected $output_xml_filename_1;
        protected $output_xml_scripture_filename;
        protected $output_json_filename;
      
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
                echo "output_filename: $output_filename" . PHP_EOL;
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
                force_crate_parent_dir($output_filename);
                $good = file_put_contents($output_filename, $content)!==FALSE && $good;
                zip_entry_close($zip_entry);
            }
            zip_close($zip);
            return $good === FALSE ? FALSE : $result;
        }

        protected function extract_word_entry_from_docx() {
            if (!$this->source_extracted_xml) {
                $source_extracted_xml_dir = $this->source_docx_filename . "_dir";
                force_crate_parent_dir($source_extracted_xml_dir);
                $entry_names = ["word/document.xml", "word/_rels/document.xml.rels", "word/media/", "word/footer1.xml", "word/footnotes.xml", "word/styles.xml", "docProps/app.xml", "docProps/core.xml"];
                $this->source_extracted_xml = $this->extract_entries_from_zip($this->source_docx_filename, $entry_names, $source_extracted_xml_dir);            
            }
            return $this->source_extracted_xml;
        }

        public function __construct(string $source_docx_filename, string $xslt_transformation_1_to_xml, string $output_xml_filename_1/*, $xslt_transformation_2_to_xml , $xslt_transformation_3_to_json, string $output_json_filename*/) {
            $this->source_extracted_xml = null;
            $this->source_docx_filename = $source_docx_filename;
            if (!$forced_source_xmls) {
                $forced_source_xmls = $this->extract_word_entry_from_docx();
                if (!$forced_source_xmls) {
                    return FALSE;
                }
            }
            
            parent::__construct($forced_source_xmls, $xslt_transformation_1_to_xml, $output_xml_filename_1);

            $this->xslt_transformation_1_to_xml = $xslt_transformation_1_to_xml;
            
            $this->output_xml_filename_1 = $output_xml_filename_1; ///$source_docx_filename . '_tmp.xml';
        }

        public function transform_to_xml_1() {
            if (count($this->source_extracted_xml) == 0) {
                return FALSE;
            }
            $output_xml_filename = $this->output_xml_filename_1;
            // if (!$output_xml_filename) {
            //     $output_xml_filename = './output/' . basename($forced_source_xml[0]) . "_out.xml"; ///'./output/output.xml';
            // }
            return $this->transform_to_xml($this->source_extracted_xml, $output_xml_filename);
        }
    }

    class DocxToXmlScripture extends DocxToXml {

        public function __construct(string $source_docx_filename, string $xslt_transformation_2_to_xml, string $output_xml_scripture_filename/*, $xslt_transformation_2_to_xml , $xslt_transformation_3_to_json, string $output_json_filename*/) {
            $dir = dirname($output_xml_scripture_filename);
            $xslt_transformation_1_to_xml = '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl';
            $this->output_xml_filename_1 = $dir . '/' . 'out1.xml';
            parent::__construct($source_docx_filename, $xslt_transformation_1_to_xml, $this->output_xml_filename_1);

            $this->output_xml_filename_1 = $this->transform_to_xml_1();
            if (!$this->output_xml_filename_1) {
                return FALSE;
            }

            $this->xslt_transformation_2_to_xml = $xslt_transformation_2_to_xml;
            ////$this->xslt_transformation_2_to_xml = $xslt_transformation_2_to_xml;
            ////$this->xslt_transformation_3_to_json = $xslt_transformation_3_to_json;
            
            $this->output_xml_scripture_filename = $output_xml_scripture_filename; ///$source_docx_filename . '_tmp.xml';
            ////$this->output_xml_scripture_filename = null;
            ////$this->output_json_filename = $output_json_filename;
        }

        protected function transform_xml_to_xml_scripture(array $output_xml_filename, string $output_xml_scripture_filename) {
            force_crate_parent_dir($output_xml_scripture_filename);
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

        public function __construct(array $source_xml_filenames, string $xslt_transformation_to_json, string $output_json_filename) {
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
            force_crate_parent_dir($output_json_filename);
            return $this->transform_with_xslt_to_json($source_xml_filenames, $this->xslt_transformation_to_json, $output_json_filename, $source_docx_filename);
        }

        public function transform_to_json() {
            $source_docx_filename = null;
            return $this->transform_xml_to_json($this->source_xml_filenames, $this->output_json_filename, $source_docx_filename);
        }

        public function get_output_json_filename() {
            return $this->output_json_filename;
        }
    }

    ///$docxToJson = new DocxToJson('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl', '/home/proxym/php-xslt-docx2json/xmltojson.xsl', '/home/proxym/php-xslt-docx2json/outut/output.json'/*TODO*/);
    ///$docxToJson = new DocxToXml('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl', '/home/proxym/php-xslt-docx2json/xmltoxml_scripture.xsl', '/home/proxym/php-xslt-docx2json/xmltojson_scripture_old.xsl', '/home/proxym/php-xslt-docx2json/outut/output.json'/*TODO*/);
    $docxToxml = new DocxToXml('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl', '/home/proxym/php-xslt-docx2json/output/calendar_2019_0.xml'/*TODO*/);
    $outputXml = $docxToxml->transform_to_xml_1();
    if ($outputXml === FALSE) {
        echo "ERROR 1" . PHP_EOL;
        exit(1);
    }

    $docxToXmlScripture = new DocxToXmlScripture('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', '/home/proxym/php-xslt-docx2json/xmltoxml_scripture.xsl', '/home/proxym/php-xslt-docx2json/output/calendar_2019_0_scripture.xml'/*TODO*/);  
    $outputXmlScripture = $docxToXmlScripture->transform_to_xml_scripture();
    /////$outputXml = $docxToJson->transform_to_json('/home/proxym/php-xslt-docx2json/output/calendar_2019_0.xml', null);
    if ($outputXml === FALSE) {
        echo "ERROR 2" . PHP_EOL;
        exit(1);
    }

    $xmlToJson = new XmlToJson([$outputXml], '/home/proxym/php-xslt-docx2json/xmltojson_scripture_old.xsl', '/home/proxym/php-xslt-docx2json/output/calendar_2019_0_scripture_old.json'/*TODO*/);  
    $outputJson = $xmlToJson->transform_to_json();
    if ($outputJson === FALSE) {
        echo "ERROR 3" . PHP_EOL;
        exit(1);
    }

    echo PHP_EOL;
?>
