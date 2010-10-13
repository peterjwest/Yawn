<?php
abstract class YawnNode {
	var $defaults = array('string' => false, 'file' => false, 'parent' => false);
	var $parent = false;
	var $parsed = false;
	var $tail = false;
	var $lazy = true;

	function __construct($options) {
		$options = array_merge($this->defaults, $options);
		if ($options['file']) $options['string'] = file_get_contents($options['file']);
		$this->tail = $options['string'];
		$this->parent = $options['parent'];
		$this->init($options);
	}
	
	function find($selectors) { return false; }
	function matches($selectors) { return false; }
	function has($regex = "") { if (preg_match("~^\s*".$regex."~", $this->tail, $match)) return $match; }
	function passTail($node) { $node->tail = $this->tail; $this->tail = false; }
	
	function get($regex = "") {
		if ($match = $this->has($regex)) {
			$this->tail = substr($this->tail, strlen($match[0]));
			return $match;
		}
	}
	
	function getUntil($string) {
		$pos = strpos($this->tail, $string);
		if ($pos !== false) {
			$inner = substr($this->tail, 0, $pos - 1);
			$this->tail = substr($this->tail, $pos + strlen($string));
			return $inner;
		}
	}

	function parsed() {
		if ($this->parent) $this->passTail($this->parent);
		$this->parsed = true;
	}
}

abstract class YawnBlock extends YawnNode {
	var $content = '';
	var $end = '';
	function init($options) { $this->content = $this->getUntil($this->end); $this->parsed(); }
	function render() { return '<!--'.$this->content.'-->'; }
}

class YawnHtml extends YawnNode {
	var $defaults = array('string' => '', 'file' => false, 'parent' => false, 'name' => '');
	var $name = '';
	var $attrs = false;
	var $children = array();
	var $child = 0;
	var $singular = false;

	function render() { 
		unset($this->defaults);
		unset($this->parent);
		$content = '<'.$this->name; 
		if ($this->attrs !== false) { 
			foreach($this->attrs as $name => $value) { $content .= ' '.$name.'="'.$value.'"'; }
			$content .= '>';
		}
		foreach($this->children as $child) { $content .= $child->render(); }
		if ($this->parsed) $content .= '</'.$this->name.'>';
		else if (!$this->tail) throw new Exception($this->name." element not closed");
		return $content.$this->tail;
	}
	
	function init($options) {
		$match = $this->get("<([^\s</>]+)");
		$this->name = $match[1];
	}
	
	function unique($selector) { return strpos($selector, "#") !== false; }

	function find($selectors) {
		$this->parseStart();
		$remaining = $this->matches($selectors);
		$found = array();
		if ($remaining) $selectors = $remaining;
		$unique = $this->unique($selectors);
		if ($remaining === '') {
			if ($unique) return array($this); 
			$found[] = $this;
		}
		if ($this->singular) return $found;
		$this->child = 0;
		while ($child = $this->child()) {
			if ($nodes = $child->find($selectors)) {
				if ($unique) return $nodes;
				$found = array_merge($found, $nodes);
			}
		}
		return $found;
	}
	
	function matches($selectors) {
		$selectors = preg_split("~\s+~", trim($selectors), 1);
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
		return isset($selectors[1]) ? $selectors[1] : '';
	}
	
	function attr($name, $value = false) {
		$this->parseStart();
		if (count(func_get_args()) > 1) {
			if ($value === false) unset($this->attrs[$name]);
			else $this->attrs[$name] = $value;
			return $this;
		}
		return isset($this->attrs[$name]) ? $this->attrs[$name] : false;
	}
	
	function child() {
		if (isset($this->children[$this->child])) return $this->children[$this->child++];
		if (!$this->parsed && $child = $this->parseChild()) {
			$this->child++;
			return $child;
		}
		return false;
	}

	function parseChild() {
		if ($this->get("</{$this->name}>")) { 
			$this->parsed(); 
			return false;
		}
		$options = array('string' => $this->tail, 'parent' => $this);
		if ($this->has("<([^\s<>]+)")) $node = new YawnHtml($options);
		else if ($this->has("<!--")) $node = new YawnComment($options);
		else if ($this->has("<!\[CDATA\[")) $node = new YawnCdata($options);
		else if ($this->has("[^<]")) $node = new YawnText($options);
		else return false;
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
			$this->attrs[$name[1]] = isset($value[1]) ? $value[1] : true;
		}
		if ($close = $this->get("(/?)>")) { if ($close[1]) { $this->singular = true; $this->parsed(); } }
		else throw new Exception("'".$this->name."' start tag not closed");
	}

	function parseComment() {
		if ($this->get("<!--"))	if(!$this->getUntil("-->")) throw new Exception("HTML comment not closed");
	}
}

class YawnComment extends YawnBlock { var $end = '-->'; }
class YawnCdata extends YawnBlock { var $end = '\]\]>'; }
class YawnText extends YawnBlock { 	function init($options) { $this->get("[^<]+"); } }