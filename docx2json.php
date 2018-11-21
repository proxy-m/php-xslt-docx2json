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

    class DocxToJson {
        private $source_docx_filename;
        private $source_extracted_xml;
        private $xslt_transformation_1_to_xml;
        private $xslt_transformation_2_to_json;
        private $output_xml_filename;
        private $output_json_filename;

        /**
         * $entry_names[0] must be root xml document entry.
         */
        static function extract_entries_from_zip(string $source_zip_filename, array $entry_names, string $output_dirname) {            
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
                self::force_crate_parent_dir($output_filename);
                $good = file_put_contents($output_filename, $content)!==FALSE && $good;
                zip_entry_close($zip_entry);
            }
            zip_close($zip);
            return $good === FALSE ? FALSE : $result;
        }

        /**
         * $from_xml_filename[0] must be root xml document.
         */
        static function transform_with_xslt(array $from_xml_filename, string $by_xsl_style) { 
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
            return [$xsl, $xmldoc];
        }

        static function beautifyOutputXmlData(string $outputXmlData): string {
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
            return $outputXmlData;
        }

        static function remove_empty_tags_from_dom_document($doc) { // Remove empty tags
			$xpath = new DOMXPath($doc);
			while (($nodes = $xpath->query('//*[not(*) and not(\'i:image\') and not(text()[normalize-space()])]')) && ($nodes->length)) {
				foreach ($nodes as $node) {
					$node->parentNode->removeChild($node);
				}
            }
            return $doc;
        }

        static function transform_with_xslt_to_xml(array $from_xml_filename, string $by_xsl_style, string $to_filename) { 
            $xsl_result = self::transform_with_xslt($from_xml_filename, $by_xsl_style);
            if (!$xsl_result || count($xsl_result)!==2) {
                return FALSE;
            }
            $xsl = $xsl_result[0];
            $xmldoc = $xsl_result[1];
            $outputXmlData = $xsl->transformToXml($xmldoc);

            $outputXmlData = self::beautifyOutputXmlData($outputXmlData);
            $result = new DOMDocument("1.0");
            $result->preserveWhiteSpace = false;
            $result->formatOutput = true;
            $result->loadXML($outputXmlData);

            self::remove_empty_tags_from_dom_document($result);

            $result->encoding = "UTF-8";
            $good = $result->save($to_filename);
            return ($good === FALSE) ? FALSE : $to_filename;
        }

        static function transform_with_xslt_to_json(array $from_xml_filename, string $by_xsl_style, string $to_filename) { 
            $xsl_result = self::transform_with_xslt($from_xml_filename, $by_xsl_style);
            if (!$xsl_result || count($xsl_result)!==2) {
                return FALSE;
            }
            $xsl = $xsl_result[0];
            $xmldoc = $xsl_result[1];
            $intermediaryDocument = $xsl->transformToDoc($xmldoc);

            $xml = $intermediaryDocument->saveXML();
            ///$displayTags = array('content', 'b', 'i', );
            
            // $result = new DOMDocument("1.0");
            // $result->preserveWhiteSpace = false;
            // $result->formatOutput = true;
            // $result->loadXML($outputXmlData);
            // $result->encoding = "UTF-8";
            $good = $intermediaryDocument->save($to_filename);
            ///$good = file_put_contents($to_filename, $intermediaryDocument)!==FALSE;
            return ($good === FALSE) ? FALSE : $to_filename;
        }

        static function force_crate_parent_dir($full_file_name) {
            $output_dir = dirname($full_file_name);
            echo "dir: $output_dir" . PHP_EOL;
            return mkdir($output_dir, 0777, true);
        }

        public function __construct(string $source_docx_filename, $xslt_transformation_1_to_xml, $xslt_transformation_2_to_json, string $output_json_filename) {
            $this->source_docx_filename = $source_docx_filename;
            $this->xslt_transformation_1_to_xml = $xslt_transformation_1_to_xml;
            $this->xslt_transformation_2_to_json = $xslt_transformation_2_to_json;
            $this->source_extracted_xml = null;
            $this->output_xml_filename = null; ///$source_docx_filename . '_tmp.xml';
            $this->output_json_filename = $output_json_filename;
        }

        private function extract_word_entry_from_docx() {
            if (!$this->source_extracted_xml) {
                $source_extracted_xml_dir = $this->source_docx_filename . "_dir";
                self::force_crate_parent_dir($source_extracted_xml_dir);
            $entry_names = ["word/document.xml", "word/_rels/document.xml.rels", "word/media/", "word/footer1.xml", "word/footnotes.xml", "word/styles.xml"];
                $this->source_extracted_xml = self::extract_entries_from_zip($this->source_docx_filename, $entry_names, $source_extracted_xml_dir);            
            }
            return $this->source_extracted_xml;
        }

        public function transform_to_xml($forced_source_xml, $output_xml_filename) {            
            if (!$forced_source_xml) {
                $forced_source_xml = $this->extract_word_entry_from_docx();
                if (!$forced_source_xml) {
                    return FALSE;
                }
            }
            if (!$output_xml_filename) {
                $output_xml_filename = './output/' . basename($forced_source_xml[0]) . "_out.xml"; ///'./output/output.xml';
            }
            self::force_crate_parent_dir($output_xml_filename);
            $this->output_xml_filename = self::transform_with_xslt_to_xml($forced_source_xml, $this->xslt_transformation_1_to_xml, $output_xml_filename);
            return $this->output_xml_filename;
        }

        protected function transform_xml_to_json(string $output_xml_filename, string $output_json_filename) {
            self::force_crate_parent_dir($output_json_filename);
            return $this->transform_with_xslt_to_json([$output_xml_filename], $this->xslt_transformation_2_to_json, $output_json_filename);
        }

        public function transform_to_json($output_xml_filename, $output_json_filename) {
            if (!$this->output_xml_filename) {
                $this->output_xml_filename = $this->transform_to_xml(null, $output_xml_filename);
                if (!$this->output_xml_filename) {
                    return FALSE;
                }
            }
            if (!$output_json_filename) {
                $output_json_filename = './output/output.json';
            }
            $this->output_json_filename = $output_json_filename;
            return $this->transform_xml_to_json($this->output_xml_filename, $this->output_json_filename);
        }

        public function get_output_xml_filename() {
            return $this->output_xml_filename;
        }

        public function get_output_json_filename() {
            return $this->output_json_filename;
        }
    }

    $docxToJson = new DocxToJson('/home/proxym/php-xslt-docx2json/input/calendar_2019_0.docx', '/home/proxym/php-xslt-docx2json/wordtoxml_xslt1.xsl', '/home/proxym/php-xslt-docx2json/xmltojson.xsl', '/home/proxym/php-xslt-docx2json/outut/output.json'/*TODO*/);
    ///$outputXml = $docxToJson->transform_to_xml(null, '/home/proxym/php-xslt-docx2json/output/calendar_2019_0.xml');
    $outputXml = $docxToJson->transform_to_json('/home/proxym/php-xslt-docx2json/output/calendar_2019_0.xml');
    echo $outputXml;

    echo PHP_EOL;
?>
