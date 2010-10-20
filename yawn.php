<?php
abstract class YawnNode {
	var $defaults = array('string' => false, 'file' => false, 'parent' => false);
	var $parsed = false, $lazy = true;
	function has($regex = "") { if (preg_match("~^\s*".$regex."~", $this->tail, $match)) return $match; }
	function passTail($node) { $node->tail = $this->tail; $this->tail = false; }
	function parsed() { if ($this->parent) $this->passTail($this->parent); $this->parsed = true; }
	function dump() { return get_class($this)."(".$this->content.")"; }
	function find($selectors) { return false; }
	function init($options) {}
	
	function __construct($options = array()) {
		$options = array_merge($this->defaults, $options);
		if ($options['file']) $options['string'] = file_get_contents($options['file']);
		$this->tail = $options['string'];
		$this->parent = $options['parent'];
		$this->init($options);
	}

	function get($regex = "") {
		if ($match = $this->has($regex)) {
			$this->tail = substr($this->tail, strlen($match[0]));
			return $match;
		}
	}
	
	function getUntil($string) {
		$pos = strpos($this->tail, $string);
		if ($pos !== false) {
			$inner = substr($this->tail, 0, $pos);
			$this->tail = substr($this->tail, $pos + strlen($string));
			return $inner;
		}
	}
}

abstract class YawnBlock extends YawnNode {
	var $content = '', $end = '';
	function init($options) { $this->content = $this->getUntil($this->end); $this->parsed(); }
	function render() { return $this->start.$this->content.$this->end; }
	function starts($options) {
		$this->tail = $options['string']; 
		if ($this->get($this->startMatch)) {
			$options['string'] = $this->tail;
			return new $this($options);
		}
	}
}

class Yawn extends YawnNode {
	var $defaults = array('string' => '', 'file' => false, 'parent' => false, 'name' => '', 'types' => array());
	var $name = '', $attrs = false, $children = array(), $child = 0, $singular = false;
	var $startMatch = "<([^\s</>!]+)";
	function unique($selector) { return strpos($selector, "#") !== false; }

	function dump() { 
		return $this->name."(".
			implode(", ", array_map(function($i) { return $i->dump(); }, $this->children)).
			($this->tail ? '"'.trim(preg_replace("~\s+~", " ", $this->tail)).'"' : null).
		")"; 
	}
	
	function starts($options) { 
		$this->tail = $options['string']; 
		if ($match = $this->get($this->startMatch)) {
			$options['string'] = $this->tail;
			$node = new $this($options); 
			$node->name = $match[1];
			return $node;
		}
	}
	
	function init($options) {
		$this->types = $options['types'] ? $options['types'] : array(
			new Yawn(array('types' => true)), new YawnComment, new YawnCdata, new YawnComment, new YawnText
		);
		if (!$this->name) {
			$match = $this->get($this->startMatch);
			$this->name = $match[1];
		}
	}
	
	function render() {
		$content = '<'.$this->name; 
		if ($this->attrs !== false) { 
			foreach($this->attrs as $name => $value) { $content .= ' '.$name.'="'.$value.'"'; }
			$content .= '>';
		}
		foreach($this->children as $child) { $content .= $child->render(); }
		if ($this->parsed) $content .= '</'.$this->name.'>';
		else if ($this->tail === false) throw new Exception($this->name." element not closed");
		return $content.$this->tail;
	}

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
			$selector = preg_split("~(?<=[\.#@])~", $selector);
			if ($selector[0] === "#" && $this->attr("id") !== $selector[1])
				return false;
			if ($selector[0] === "." && !in_array($selector[1], explode(" ", $this->attr("class")))) 
				return false;
			if ($selector[0] === "@" && !in_array($selector[1], explode(" ", $elem->attr("stim:id"))))
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
		$options = array('string' => $this->tail, 'parent' => $this, 'types' => $this->types);
		$this->tail = '';
		$node = false;
		foreach($this->types as $type)
			if ($node = $type->starts($options)) return $this->children[] = $node;
		throw new Exception("Can't parse node from: '{$this->tail}'");
	}
	
	function parseStart() {
		if ($this->attrs !== false) return;
		$this->attrs = array();
		while ($name = $this->get("\s+([^\s=</>]+)")) {
			if ($this->get("=")) 
				($value = $this->get('"([^"]*)"')) ||
				($value = $this->get("'([^']*)'")) ||
				($value = $this->get("([^\s</>]*)"));
			$this->attrs[$name[1]] = isset($value[1]) ? $value[1] : true;
		}
		if ($close = $this->get("(/?)>")) { if ($close[1]) { $this->singular = true; $this->parsed(); } }
		else throw new Exception("'".$this->name."' start tag not closed");
	}
}

class YawnComment extends YawnBlock { var $start = "<!--", $startMatch = "<!--", $end = '-->'; }
class YawnCdata extends YawnBlock { var $start = "<![CDATA[", $startMatch = "<!\[CDATA\[", $end = ']]>'; }
class YawnText extends YawnNode {
	function render() { return $this->content; }
	function init($options) {}
	function starts($options) {
		$this->tail = $options['string']; 
		if ($match = $this->get("(.+?)(?=\s*<[^\s])")) {
			$options['string'] = $this->tail;
			$node = new $this($options);
			$node->content = $match[1];
			$node->parsed();
			return $node;
		}
	}
	
}