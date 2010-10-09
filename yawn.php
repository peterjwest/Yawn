<?php
class YawnNode {
	var $attrs = false;
	var $children = array();
	var $done = false;
	var $tail = "";
		
	function __construct($html) { $this->tail = $html; }
	function unique($selector) { return preg_match("~#[^\s]*$~", $selector); }
	
	function find($selectors) { 
		$this->attrs();
		$unique = $this->unique($selectors)
		$found = array();
		$remaining = $this->matches($selectors);
		if ($remaining === true) {
			$found[] = $this;
			if ($unique) return $found;
		}
		while ($child = $this->child()) {
			if (strlen($remaining) > 0 && $nodes = $child->find($remaining)) {
				if ($unique) return $nodes;
				$found = array_merge($found, $nodes);
			}
			if ($nodes = $child->find($selectors)) {
				if ($unique) return $nodes;
				$found = array_merge($found, $nodes);
			}
		}
		return $found;
	}

	function matches($selectors) {
		$selectors = preg_split("[\s]+",$selectors,1);
		foreach (preg_split("~(?=[\.#@])~",$selectors[0]) as $selector) {
			if (substr($selector,0,1) === "#")
				if ($this->attr("id") !== substr($selector,1)) return false;
			if (substr($selector,0,1) === ".")
				if (!in_array(substr($selector,1), explode(" ", $this->attr("class")))) 
					return false;
			if (substr($selector,0,1) === "@")
				if (!in_array(substr($selector,1), explode(" ", $elem->attr("stim:id")))) 
					return false;
		}
		return isset($selectors[1]) $selectors[1] : true;
	}

}