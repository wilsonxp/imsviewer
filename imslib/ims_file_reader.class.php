<?php


/**
 *
 */
class ims_file_reader {

	/**
	 *
	 */
	function read_folder($sourse_path) {
		$course = $course_path;

		$xmlstr = file_get_contents($course_path."/imsmanifest.xml");


		$course = new SimpleXMLElement($xmlstr);

		$organizations = $course->organizations;
		$resources = $course->resources;

		echo $course->organizations->organization->title."<br>";

		foreach ($course->organizations->organization->item as $organization) {
			$attrib = $organization->attributes();
			echo "<li>".$organization->title."</li>";
			$res =  find_resource_by_id($course, $attrib['identifierref']);
			print_r($organization->attributes());

		}


		return $course;
	}


	/**
	 *
	 */
	function read_zip() {

	}


	/**
	* Parse the contents of a IMS package's manifest file
	* @param string $manifestfilecontents the contents of the manifest file
	* @return array
	*/
	function imscp_parse_manifestfile($manifestfilecontents) {
		$doc = new DOMDocument();
		if (!$doc->loadXML($manifestfilecontents, LIBXML_NONET)) {
			return null;
		}

		// we put this fake URL as base in order to detect path changes caused by xml:base attributes
		$doc->documentURI = 'http://grrr/';

		$xmlorganizations = $doc->getElementsByTagName('organizations');
		if (empty($xmlorganizations->length)) {
			return null;
		}
		$default = null;
		if ($xmlorganizations->item(0)->attributes->getNamedItem('default')) {
			$default = $xmlorganizations->item(0)->attributes->getNamedItem('default')->nodeValue;
		}
		$xmlorganization = $doc->getElementsByTagName('organization');
		if (empty($xmlorganization->length)) {
			return null;
		}
		$organization = null;
		foreach ($xmlorganization as $org) {
			if (is_null($organization)) {
				// use first if default nor found
				$organization = $org;
			}
			if (!$org->attributes->getNamedItem('identifier')) {
				continue;
			}
			if ($default === $org->attributes->getNamedItem('identifier')->nodeValue) {
				// found default - use it
				$organization = $org;
				break;
			}
		}

		// load all resources
		$resources = array();

		$xmlresources = $doc->getElementsByTagName('resource');
		foreach ($xmlresources as $res) {
			if (!$identifier = $res->attributes->getNamedItem('identifier')) {
				continue;
			}
			$identifier = $identifier->nodeValue;
			if ($xmlbase = $res->baseURI) {
				// undo the fake URL, we are interested in relative links only
				$xmlbase = str_replace('http://grrr/', '/', $xmlbase);
				$xmlbase = rtrim($xmlbase, '/').'/';
			} else {
				$xmlbase = '';
			}
			if (!$href = $res->attributes->getNamedItem('href')) {
				continue;
			}
			$href = $href->nodeValue;
			if (strpos($href, 'http://') !== 0) {
				$href = $xmlbase.$href;
			}
			// href cleanup - Some packages are poorly done and use \ in urls
			$href = ltrim(strtr($href, "\\", '/'), '/');
			$resources[$identifier] = $href;
		}

		$items = array();
		foreach ($organization->childNodes as $child) {
			if ($child->nodeName === 'item') {
				if (!$item = imscp_recursive_item($child, 0, $resources)) {
					continue;
				}
				$items[] = $item;
			}
		}

		return $items;
	}

	function imscp_recursive_item($xmlitem, $level, $resources) {
		$identifierref = '';
		if ($identifierref = $xmlitem->attributes->getNamedItem('identifierref')) {
			$identifierref = $identifierref->nodeValue;
		}

		$title = '?';
		$subitems = array();

		foreach ($xmlitem->childNodes as $child) {
			if ($child->nodeName === 'title') {
				$title = $child->textContent;

			} else if ($child->nodeName === 'item') {
				if ($subitem = imscp_recursive_item($child, $level+1, $resources)) {
					$subitems[] = $subitem;
				}
			}
		}

		return array('href'     => isset($resources[$identifierref]) ? $resources[$identifierref] : '',
					'title'    => $title,
					'level'    => $level,
					'subitems' => $subitems,
					);
	}
}