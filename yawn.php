<?php
class YawnNode {
	var $name = "";
	var $attrs = false;
	var $children = array();
	var $singular = false;
	var $parsed = false;
	var $tail = false;
	var $lazy = true;

	function __construct($html) {
		$this->tail = $html; 
		$match = $this->get("<([^\s</>]+)");
		$this->name = $match[1];
	}
	
	function unique($selector) { return strpos("#", $selector) !== false; }
	function has($regex = "") { if (preg_match("~^\s*".$regex."~", $this->tail, $match)) return $match; }
	
	function get($regex = "") {
		if ($match = $this->has($regex)) {
			$this->tail = substr($this->tail, strlen($match[0]));
			return $match;
		}
	}
	
	function getFirst($string) {
		$pos = strpos($this->tail, $string);
		if ($pos !== false) {
			$inner = substr($this->tail, 0, $pos - 1);
			$this->tail = substr($this->tail, $pos + strlen($string));
			return $inner;
		}
	}
	
	function find($selectors) {
		$this->parseStart();
		$unique = $this->unique($selectors);
		$found = array();
		$remaining = $this->matches($selectors);
		if ($remaining === true) {
			$found[] = $this;
			if ($unique) return $found;
		}
		if ($this->singular) return $found;
		while ($this->children[] = $child = $this->parseChild()) {
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
		$selectors = preg_split("\s+",$selectors,1);
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
		return isset($selectors[1]) ? $selectors[1] : true;
	}
	
	function attr($name, $value = false) {
		$this->parseStart();
		if (count(func_get_args()) > 1) {
			if ($value === false) unset($this->attr[$name]);
			else $this->attr[$name] = $value;
			return $this;
		}
		return isset($this->attr[$name]) ? $this->attr[$name] : false;
	}
	
	function parseChild() {
		if ($this->get("</{$this->name}>")) { 
			$this->parsed = true; 
			return false;
		}
		if ($this->has("<([^\s<>]+)")) $node = new YawnNode($this->tail);
		else if ($this->has("<!--")) $node = new YawnComment($this->tail);
		else if ($this->has("<!\[CDATA\[")) $node = new YawnCdata($this->tail);
		else $node = new YawnText($this->tail);
		$this->children[] = $node;
		$this->tail = false;
		return $node;
	}
	
	function parseStart() {
		if ($this->attrs !== false) return;
		$this->attrs = array();
		while ($name = $this->get("\s+([^\s=</>]+)")) {
			if ($this->get("=")) 
				($value = $this->get('"([^"]*)"')) || ($value = $this->get("'([^']*)'")) || ($value = $this->get("([^\s</>]*)"));
			$this->attrs[] = array($name[1], isset($value[1]) ? $value[1] : true);
		}
		if ($close = $this->get("(/?)>")) { if ($close[1]) { $this->singular = true; $this->parsed = true; } }
		else throw new Exception("'".$this->name."' start tag not closed");
	}

	function parseComment() {
		if ($this->get("<!--"))	if(!$this->getFirst("-->")) throw new Exception("HTML comment not closed");
	}
}

class YawnComment extends YawnNode {}
class YawnCdata extends YawnNode {}
class YawnText extends YawnNode {}