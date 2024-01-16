<?php
namespace AuntieWarhol\MVCish\View\Render;

/*
	Ok, so this started out as a way to make menulists & selectlists, and it
	sorta grew from there. Now it's a crazy complicated (but powerful!) thing
	that even I barely know how to use. I don't actually recommend it.

	Except maybe for menulists & selectlists. 

	TODO
		- add more convience methods for common constructs
		- rename some things for better clarity, particularly 'tag',
			which means two different things in node/nodelist context
		- maybe make this all work with DOMNode objects?

	Notes:
		- this does NOT encode 'string' nodes or 'content' values through
			htmlspecialchars; raw html is valid. so make sure any output is
			encoded and/or purified beforehand.	can use $MVCish->cleanOutput().
			We do use htmlspecialvars(ENT_QUOTES) on tag attribute values.
*/

class HTML {

	public function __construct() {	}

	public $debug = 0;

	private static $void_tags = [
		'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img',
		'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
	];
	private static $_void_tag_map = null;
	public static function is_void_tag($tag) {
		if (empty(self::$_void_tag_map)) {
			self::$_void_tag_map = [];
			foreach(self::$void_tags AS $t) {
				self::$_void_tag_map[$t] = true;
			}
		}
		return isset(self::$_void_tag_map[$tag]) ?
			self::$_void_tag_map[$tag] : false;
	}

	private static $_child_tags_for = [
		'ul'     => 'li',
		'ol'     => 'li',
		'dl'     => ['dt','dd' ],
		'table'  => ['tr' => ['td|th']],
		'select' => 'option'
	];
	private static function _child_tag_for($tag) {
		return isset(self::$_child_tags_for[$tag]) ?
			self::$_child_tags_for[$tag] : $tag;
	}
	private static function _alt_constructs_for($tag) {
		switch($tag) {
			case 'table':
				return [
					['thead' => self::_child_tag_for('table')],
					['tbody' => self::_child_tag_for('table')],
					['tfoot' => self::_child_tag_for('table')],
				];
		}
		return null;
	}


	/* generates an attributes list; expects:
		$attr = $array_of_attributes|$string|$callable_generator
	*/
	public function attributes($attr,$opts=[]) {
		$html = '';
		if (is_string($attr)) {
			$html = $attr;
		}
		elseif (is_array($attr)) {
			$pairs = [];
			foreach ($attr AS $k => $v) {
				if (is_array($v)) {
					//remove dupes from array
					$v = implode(" ",array_unique($v));
				}
				if (is_bool($v)) {
					// for the 'value' attribute only, stringify booleans to 'true','false'
					if ($k == 'value') {
						$pairs[] = $k.'="'.($v ? 'true' : 'false').'"';
					}
					elseif ($v === true) {
						//boolean attribute, no value (eg 'selected') -- false not an option
						//$pairs[] = $k.'="'.$k.'"'; 	//this adds it like 'selected="selected"
						$pairs[] = $k;              	//this adds it like 'selected'
					
					}
				}
				else {
					//remove dupes from string ($v or imploded $v)
					$v = implode(" ",array_unique(explode(" ",$v)));
					$pairs[] = $k.'="'.htmlspecialchars($v,ENT_QUOTES).'"';
				}
			}
			$sep = isset($opts['separator']) ? $opts['separator'] : ' ';
			$html = implode($sep,$pairs);
		}
		elseif (is_callable($attr)) {
			$html = $attr();
		}
		return $html;
	}


	/* generates an <a><img></a> link from simple data; expects:
		$data = [
			'src' => $src,
			'height' => $height, 'width' => $width,
			'alt' => $alt, 'class' => $class,
			'a' => [ 'class' => $class, 'href' => $href ]
		]
		all keys optional. link only generated if 'a' present. img
		only generated if at least one attribute present.

		essentially a convenience wrapper around a more complex
		call to this->node()
	*/
	public function imglink($data,$opts=[]) {
		$node = null;

		$attr = [];
		foreach(['src','height','width','alt','class'] AS $field) {
			if (isset($data[$field])) {
				$attr[$field] = $data[$field];
			}
		}
		if (count($attr)) {
			$node = ['tag' => 'img', 'attr' => $attr];
		}
		if (isset($data['a'])) {
			$node = ['tag' => 'a', 'attr' => $data['a'], 'content' => $node];
		}
		return $this->node($node,null,$opts);
	}


	/* generates an html form. accepts:
			$form = [ attr => [], 'tag' => 'ul|dl', 'nodes' => $nodes ]
		or:
			$form = [ attr => [], nodelist = ['tag' => 'ul|dl', 'nodes' => $nodes] ]

		If you send:
			$form = [ attr => [],
				'tag' => $tag1,	'nodelist = ['tag' => $tag2, 'nodes' => $nodes2]
			]
		we will use:
			$nodelist = ['tag' => $tag1, 'nodes' => $nodes2]

		Or send:
			$form = [ attr => [], nodelist = $nodes ]
		And we won't apply a tag to the nodelist

	*/
	public function form($form,$opts=[]) {

		$form['attr'] = isset($form['attr']) ? $form['attr'] : [];
		foreach (['action' => $_SERVER['REQUEST_URI'], 'method' => "post", 'name' => 'form_'.time()] AS $f=>$default) {
			$form['attr'][$f] = isset($form['attr'][$f]) ? $form['attr'][$f] : $default;
		}

		$hidden = '';
		if (isset($form['hidden'])) {
			/* if we got hidden fields, add them before the nodelist;
				renders as a list without a tag. Note we don't actually check that your
				list renders hidden fields. So you have to send, eg:
				[ 'tag' => 'input', 'attr' => ['name'=>"foo", 'type'=>"hidden", 'value'=>"bar"]],
				And I guess you could also use it for something/anything else.
				But then maybe make that a different option? Or make this more generic?
				And then make this smart enough to default the tag, etc for hidden fields?
				Also note, there's no reason you can't add hidden fields to the main
				nodelist. But that might cause css issues if hidden elements in the list
				take up space with margin, padding etc.
			*/
			$hidden = $this->nodelist([
				'tag' => false, 'nodes' => $form['hidden']
			],$opts);
			unset($form['hidden']);
		}

		$nodelist = [];
		if (!isset($form['tag']) && isset($form['nodelist']) && !isset($form['nodelist']['tag'])) {
			$nodelist = $form['nodelist'];
			unset($form['nodelist']);
		}
		else {
			if (isset($form['nodes'])) {
				$nodelist = [
					'nodes' => $form['nodes'],
					'tag' => isset($form['tag']) ? $form['tag'] : null
				];
				unset($form['nodes']);
			}
			if (isset($form['nodelist'])) {
				//if we got both, local tag/nodes takes precedence
				$nodelist = array_merge($form['nodelist'],$nodelist);
				unset($form['nodelist']);
			}
			if (!isset($nodelist['tag'])) {
				//default to <dl><dt><label></dt><dd><input></dl>
				$nodelist['tag'] = isset($form['tag']) ? $form['tag'] : 
					['dl' => ['spliton_label' => true]];
			}
		}

		$nodelist_html = $this->nodelist($nodelist,$opts);
		$node = [
			'tag' => 'form', 'attr' => $form['attr'],
			'content' => $hidden.$nodelist_html
		];
		$name = $form['attr']['name'];
		unset($form['tag']);
		unset($form['attr']);
		$node = array_merge($form,$node);
		$html = $this->node($node,$nodelist,$opts);

		return "\n".implode("\n",[
			"<!-- begin form $name -->",$html,"<!-- end form $name -->"
		]) ."\n";
	}

	/* convenience wrapper which generates a select list node; expects:
		$selectops = array of options, in the form:
			[ [k1=>v1],[k2=v2] ]
		or with attributes for the option tag:
			[ [k1=>v1,'attr'=>[]],[k2=v2,'attr'=>[]] ]


		$basenode = a standard datanode to which we should add our content;
			another way of saying 'array with label & attr keys'
		$opts = [
			'selected' => $k // the k value to be marked selected if found
			'asdata' => true|false //default false, render now or just return data
			'option_attr' => [k1=$attr,k2=>$attr] // another way to send option attributes (will merge if both),
			'placeholderOpt' => k // option key to set as placeholder (disabled,hidden,class='placeholder')
				(or send 'option_attr' => [''=>['class'=>'placeholder']] to use placeholder style
				without setting hidden/disabled)
			'item_attr' => $attr // item_attr to be added to the resulting selectlist parent node
		]
	*/
	public function selectlist($selectopts,$basenode=[],$opts=[]) {
		if(!isset($opts['asdata'])) $opts['asdata'] = false;
		if (!isset($opts['option_attr'])) $opts['option_attr'] = [];

		$selectoptions = [];
		foreach ($selectopts AS $selectoption) {
			$attr = [];
			if (isset($selectoption['attr'])) {
				$attr = $selectoption['attr'];
				unset($selectoption['attr']);
			}

			$k = key($selectoption); $v = $selectoption[$k];
			if (isset($opts['option_attr'][$k]))
				$attr = array_merge($attr,$opts['option_attr'][$k]);

			$selectopt = [
				'item_attr' => array_merge(['value' => $k],$attr),
				'content' => htmlspecialchars($v)
			];
			if (isset($opts['selected'])) {
				if (is_array($opts['selected'])) {
					if (in_array($k,$opts['selected'])) {
						$selectopt['item_attr']['selected'] = true;
					}
				}
				elseif ($opts['selected'] == $k) {
					$selectopt['item_attr']['selected'] = true;
				}
			}
			if (isset($opts['placeholderOpt']) && ($opts['placeholderOpt'] === $k)) {
				$selectopt['item_attr'] = array_merge($selectopt['item_attr'],[
					'disabled'=>true, 'hidden'=>true,'class'=>'placeholder'
				]);
			}

			$selectoptions[] = $selectopt;
		}
		$nodelist = ['tag' => ['tag'=>'select'], 'nodes' => $selectoptions];
		$node     = ['selectlist' => array_merge_recursive($nodelist,$basenode)];
		if (isset($opts['item_attr'])) {
			$node['item_attr'] = $opts['item_attr'];
		}
		return $opts['asdata'] ? $node : $this->node($node);
	}

	public function checkboxlist($boxes,$basenode=[],$opts=[]) {
		if(!isset($opts['asdata'])) $opts['asdata'] = false;
		if(!isset($opts['name'])) $opts['name'] = 'checkbox_'.time();
		$checkboxes = [];
		foreach ($boxes AS $box) {
			$attr = [];
			if (isset($opts['options_attr'])) { // applies to all options unless overridden
				$attr = $opts['options_attr'];
			}
			if (isset($box['attr'])) {
				$attr = $box['attr'];
				unset($box['attr']);
			}
			$k = key($box); $v = $box[$k];
			if (isset($opts['option_attr'][$k])) // applies to specified option
				$attr = array_merge($attr,$opts['option_attr'][$k]);

			$checkbox = [
				'label' => ['content' => $v, 'labelafter' => true, 'class'=>'checkbox-label'],
				'tag' => 'input', 'attr' => array_replace([
					'type' => 'checkbox','value' => $k,'name' => $opts['name'].'[]'
				],$attr)
			];
			if (isset($opts['selected']) && is_array($opts['selected']) && (in_array($k,$opts['selected']))) {
				$checkbox['attr']['checked'] = 'checked';
			}
			$checkboxes[] = $checkbox;
		}
		$nodelist = ['tag' => ['tag' => 'ul','attr'=>['class'=>'list-unstyled']],'nodes' => $checkboxes];
		$node    = ['checkboxlist' => array_merge_recursive($nodelist,$basenode)];
		if (isset($opts['item_attr'])) {
			$node['item_attr'] = $opts['item_attr'];
		}
		return $opts['asdata'] ? $node : $this->node($node);
	}

	public function radiolist($radios,$basenode=[],$opts=[]) {
		if(!isset($opts['asdata'])) $opts['asdata'] = false;
		if(!isset($opts['name'])) $opts['name'] = 'radio_'.time();
		$radiolist = [];
		foreach ($radios AS $r) {
			$k = key($r); $v = $r[$k];

			$radio = [
				'label' => ['content' => $v, 'labelafter' => true],
				'tag' => 'input', 'attr' => [ 'type' => 'radio',
					'value' => $k, 'name' => $opts['name']
				]
			];
			if (isset($opts['selected']) && ($opts['selected'] == $k)) {
				$radio['attr']['checked'] = 'checked';
			}
			$radiolist[] = $radio;
		}
		$nodelist = ['tag' => ['tag' => 'ul','attr'=>['class'=>'list-unstyled']],'nodes' => $radiolist];
		$node    = ['radiolist' => array_merge_recursive($nodelist,$basenode)];
		return $opts['asdata'] ? $node : $this->node($node);
	}
	

	/* convenience wrapper which generates a ul-list by default */
	public function menulist($nodelist,$opts=[]) {
		if (!isset($opts['tag'])) {
			$opts['tag'] = 'ul';
		}
		return $this->nodelist($nodelist,$opts);
	}

	/* generate a commonly-formatted modal */
	public function modal($opts=[]) {

		$id     = isset($opts['id'])     ? $opts['id']     : null;
		$body   = isset($opts['body'])   ? $opts['body']   : null;
		$footer = isset($opts['footer']) ? $opts['footer'] : null;
		$header = isset($opts['header']) ? $opts['header'] : null;

		if (!$header) {
			$title  = $opts['title'];
			$header = ['nodelist'=>[
				['tag'=>'button','attr'=>['type'=>'button','class'=>'close',
						'data-dismiss'=>'modal','aria-hidden'=>'true'
					],'content'=>'×'
				],
				['tag'=>'h3','attr'=>['id'=>$id.'ModalLabel'],'content'=>$title]
			]];
		}

		return ['tag'=>'div', 'attr'=>['id'=>$id,
				'class'=>'modal fade', 'tabindex'=>'-1',
				'role'=>'dialog', 'aria-labelledby'=>$id.'ModalLabel','aria-hidden'=>'true'
			], 'content' => [
				'tag'=>'div','attr'=>['class'=>'modal-dialog'],'content'=> [
					'tag'=>'div','attr'=>['class'=>'modal-content'],'content'=> [
						'nodelist'=>[
							['tag'=>'div','attr'=>['class'=>"modal-header"],
								'content' => $header ],
							['tag'=>'div','attr'=>['class'=>"modal-body"],
								'content' => $body   ],
							['tag'=>'div','attr'=>['class'=>"modal-footer"],
								'content' => $footer ]
						]
					]
				]
			]
		];
	}

	/*
		NODES: nodeisa(), node(), _datanode(), nodebyref()
	*/


	/* what sort of node is it? */
	public function nodeisa($node) {
		//a node can be a literal string
		if (is_string($node)) {
			return 'string';
		}
		//or a generator callback/closure
		elseif (is_callable($node)) {
			return 'callable';
		}
		// or an array/object of data. see _datanode
		elseif (is_array($node)) {
			return 'datanode';
		}
		return false;
	}

	/* generates a single node. called from nodelist or can call alone */
	public function node($node,$nodelist=null,$opts=[]) {
		return $this->nodebyref($node,$nodelist,$opts);
	}
	// normally, and any time you want to pass anonymous node, use node();
	// however in some cases you may need to use nodebyref(); _datanode() will
	// eat the keys it uses, and you will still have what's left.
	// see label handling in _datanode()
	public function nodebyref(&$node,$nodelist=null,$opts=[]) {

		switch ($this->nodeisa($node)) {
			case 'string':
				return $node;

			case 'callable':
				//node is a callable 'generator' function. receives no args
				return $node();

			case 'datanode':
				return $this->_datanode($node,$nodelist,$opts);
		}
	}

	/* helper to above which generates a node from a data hash/object; accepts:

		$node = [ 'content' => $nodecontent ]									// returns: content
		$node = [ 'tag' => $t, 'attr' => $attr ]								// returns: <tag %attr></tag>
		$node = [ 'tag' => $t, 'attr' => $attr, 'content' => $nodecontent  ]	// returns: <tag %attr>content</tag>
		$node = [ 'icon' => $i, 'title' => $title, 'cformat' => $cf ] //see MAGIC MENU NODE below

		Can add these keys to the above:                 //if they were all added, they would generate: 
		'before'   => $nodeable_content                  //  before+content
		'after'    => $nodeable_content                  //  before+content+after
		'a|href'   => $link_or_anchor_string_or_object   //  <link>before+content+after</link>
		'label'    => $label_string_or_object            //  <label><link>before+content+after</link></label>
		'nodelist' => $nodelist                          //  <label><link>before+content+after</link></label>nodelist

		# $nodeable_content == anything which node() can turn into data;
		# as in, another node, so, a string, callable, or datanode
	*/
	private function _datanode(&$node,$nodelist=null,$opts=[]) {
		$html = '';

		/* SLUSHY attributes: if you send any of:

				[ 'a|href' => $foo, 'attr' => $attr ]
				[ 'tag'    => $foo, 'attr' => $attr ]
				[ 'label'  => $foo, 'attr' => $attr ]

			we will try to Do What You Mean, and use 'attr' with the anchor, $tag,
			or label as expected. But if you need to send more than one of the above,
			then we have a problem. In that case, you have to send like:

				[
					'tag' => [ tag => $tag, attr => $attr ],
					'label' => [ label => $label, attr => $attr ],
					'a' => [ a => $a, attr => $attr ],
				]
		*/
		$attr = null;
		if (isset($node['attr'])) {
			$attr = $node['attr'];
			unset($node['attr']);
		}

		/* if we get ['stuff'] (single element array where index of element is 0),
			 make it ['content' => stuff] */
		if ((count($node) == 1) && (array_key_exists(0,$node))) {
			$node = [ 'content' => $node[0] ];
		}

		//can send the key 'content'; 'content' can be anything a node can be;
		if (!empty($node['content'])) {
			$html = $this->nodebyref($node['content'],$nodelist,$opts);
			unset($node['content']); // eat what we use
		}

		//can send the key 'form' and we'll pass to that.
		// could also send ['tag' => 'form', 'content' => ['nodelist' => $form_nodelist]],
		// and that will properly build a form as instructed, but using this tag instead
		// means we'll call the >form method, which sets some defaults and such.
		// (like, defaulting method="post" for the form, or 'dl' for the list tag.)
		// appends, so content & form = content.form
		if (isset($node['form'])) {
			$html .= $this->form($node['form'],$opts);
			unset($node['form']);
		}

		//can send the keys 'tag' & 'attr', and we will generate the designated $tag,
		//with attributes & closing tag, wrapped around above content if also sent
		if ((isset($node['tag']) && $node['tag'] == true)) {
/* TODO here?
						if (is_array($tag['tag'])) {
							//recursive merging may have created an array, when
							//we really wanted an override. take the last one added
							//that is an actual string (eg skip 'true')
							foreach (array_reverse($tag['tag']) AS $t) {
								if (is_string($t)) {
									$tag['tag'] = $t; break;
								}
							}
						}
*/
			$tag = $tag_attr = null;
			if (is_string($node['tag'])) {
				$tag  = $node['tag'];
				$tag_attr = $attr;
				unset($attr);
			}
			//set tag=true to use opts || default=div
			elseif ($node['tag'] === true) {
				$tag = isset($opts['tag']) ? $opts['tag'] : 'div';
				$tag_attr = $attr;
				unset($attr);
			}
			elseif (is_array($node['tag'])) {
				if (isset($node['tag']['tag'])) {
					$tag  = $node['tag']['tag'];
					$tag_attr = $node['tag']['attr'];
				}
				else {
					//recursive default merging may have created an array, when
					//we really wanted an override. take the last one added
					//that is an actual string (skip 'true'); or use default
					foreach (array_reverse($node['tag']) AS $t) {
						if (is_string($t)) {
							$found = $t; break;
						}
					}
					$tag = isset($found) ? $found : (isset($opts['tag']) ? $opts['tag'] : 'div');
					$tag_attr = $attr;
					unset($attr);
				}
			}
			unset($node['tag']);

			/* FiXME -- need parent render object here?
				-- this is for the 'tags' key in a $template definition in render.php;
				not using it at the moment. it won't work until this is fixed/replaced.	probably 
			if ($template_opts = (isset($render->current_template['tags'][$tag]) ?
				$render->current_template['tags'][$tag] : false)
			) {
				if (isset($template_opts['attr'])) {
					$tag_attr = array_merge_recursive($template_opts['attr'],$tag_attr);
				}
			}
			*/

			$pieces = [$tag];				
			if (!empty($tag_attr)) 
				$pieces[] = $this->attributes($tag_attr);

			$taghtml = '<'.implode(' ',$pieces);

			//if this is a 'void' tag
			if ($this->is_void_tag($tag)) {
				//sending 'content' with a void tag doesn't make much sense.
				//if you do it, I'm going to add $content after the tag.
				$taghtml .= ' />'.$html;
			}
			else {
				$taghtml .= implode("",['>',$html,'</'.$tag.'>']);
			}
			$html = $taghtml;
		}


		/* MAGIC MENU NODE: otherwise, if we haven't generated html above,
			we presume you are generating a menu. By default we look for
			'icon' and 'title', and generate:

				<i class="$icon"></i><span>$title</span>

			You can customize this somewhat with the 'cformat' param. In
			a typical Menu scenario, we	will also pick up 'href' and 'attr'
			below, to finally make:

				<a href="$href" $attr><i class="$icon"></i><span>$title</span></a>
		*/
		if (empty($html)) {

			$cformat = !empty($node['cformat']) ? $node['cformat'] : [ 'icon','title' ];
			unset($node['cformat']);
			foreach ($cformat AS $f) {
				if (is_string($f)) {
					if (($f == 'icon') && (isset($node['icon']))) {
						$html .= $this->node([
							'tag' => 'i', 'attr' => ['class' => $node['icon'] ]
						],$nodelist,$opts);
						unset($node['icon']);
					}
					elseif (($f == 'title') && (isset($node['title']))) {
						if (is_string($node['title'])) {
							$titlenode = ['tag' => 'span', 'content' => $node['title']];
						}
						else {
							$titlenode = $node['title'];
						}
						$html .= $this->node($titlenode,$nodelist,$opts);
						unset($node['title']);
					}
				}
				else {
					foreach ($f AS $k => $v) {
						if (is_callable($v)) {
							$html .= $v();
						}
					}
				}
			}
		}

		//TODO maybe each of the blocks to handle a tagset below should be a function? */

		/* note these two happend after the main tag or content is generated, but before
			the link and label wraps. So before+content+after is all contained in the wraps
		*/
		// if 'before' is set, prepend to whatever we generated above
		// if 'after' set, append to whatever we generated above
		$beforeafter = [];
		foreach(['before','after'] AS $which) {
			if (isset($node[$which])) {
				$beforeafter[$which] = $this->nodebyref($node[$which],$nodelist,$opts);
				unset($node[$which]);
			}
		}
		if (count($beforeafter)) {
			$html = implode('',[
				isset($beforeafter['before']) ? $beforeafter['before'] : '',
				$html,
				isset($beforeafter['after']) ? $beforeafter['after'] : ''
			]);
		}	

		/* if 'a' | 'href' set, wrap whatever we've generated in an anchor/link; can send:
			[ 'href' => $url,  'attr' => $attr ]  // <a href="$url" %attr>$html</a>    *
			[ 'a'    => $name, 'attr' => $attr ]  // <a name="name" %attr>$html</a>    *
			[ 'href' => [ 'href' => $url,  'attr' => $attr ] // <a href="$url" %attr>$html</a>
			[ 'a'    => [ 'name' => $name, 'attr' => $attr ] // <a href="$url" %attr>$html</a>
			[ 'href' => [ 'href' => $url, name => $name, foo=>$bar ] // <a href="$url" name="$name" foo="$bar">$html</a>
			[ 'a'    => [ 'href' => $url, name => $name, foo=>$bar ] // <a href="$url" name="$name" foo="$bar">$html</a>

				* bearing in mind 'attr' clash if also sending tag or label

			You can't send both 'a' and 'href'. 'a' takes precedence.
		*/
		$anchorlink = null;
		foreach([
			//default <a name=""></a>$html
			'a'    => ['key' => 'name', 'wrap' => false],
			//default <a href="">$html</a>
			'href' => ['key' => 'href', 'wrap' => true]
		] AS $k => $def) {
			if (isset($node[$k])) {


				if (is_object($node[$k]) && method_exists($node[$k], '__toString')) {
					$node[$k] = $node[$k]->__toString();
				}

				if (is_string($node[$k])) {
					$anchorlink = [$def['key'] => $node[$k]];
				}
				else if (is_array($node[$k])) {
					$anchorlink = $node[$k];
				}
				unset($def['key']);
				foreach($def AS $attrib => $v) {
					if (!isset($anchorlink[$attrib]))
						$anchorlink[$attrib] = $v;
				}

				//note if you send 'a', we don't unset 'href' (because we break);
				//if you're clever, you could use something that had both in 
				//some circumstance to your advantage. probably don't be clever.
				unset($node[$k]);
				break;
			}
		}
		if ($anchorlink) {

			$anchoropts = [];
			foreach([
				'wrap', //<a>$html</a> or <a></a>$html?
				'tag', 'attr' //same as node expects; we intercept
				] AS $opt
			) {
				if (isset($anchorlink[$opt])) {
					$anchoropts[$opt] = $anchorlink[$opt];
					unset($anchorlink[$opt]);
				}
			}
			//if tag was not set, default it
			if (!isset($anchoropts['tag'])) {
				$anchoropts['tag'] = 'a';
			}

			$anchor_attr = null;
			if (isset($attr)) {
				if (is_array($attr)) {
					//expected case, if attr is sent. merge attr array with anchorlink array
					$anchor_attr = array_merge($attr,$anchorlink);
				}
				else {
					//well, this may not do what you want, but if we got string|callable attr,
					// call ->attributes to generate for 'attr' and 'anchorlink' separately,
					// merge here and send as a string
					$anchor_attr = implode(' ',[
						$this->attributes($attr,$opts),
						$this->attributes($anchorlink,$opts)
					]);
				}
				unset($attr);
			}
			else {
				$anchor_attr = $anchorlink;
			}
			if (isset($anchoropts['attr'])) {
				$anchor_attr = array_merge($anchor_attr,$anchoropts['attr']);
			}

			// <a>$html</a>
			if ($anchoropts['wrap']) {
				$html = $this->node([
					'tag' => $anchoropts['tag'], 'attr' => $anchor_attr, 'content' => $html
				],$nodelist,$opts);
			}
			// or <a></a>$html
			else {
				$html = $this->node([
					'tag' => $anchoropts['tag'], 'attr' => $anchor_attr, 'content' => ''
				],$nodelist,$opts) . $html;
			}
		}

		/* if 'label' set, wrap everything we've generated in a label */
		if (isset($node['label'])) {
			$label = $node['label'];
			unset($node['label']);

			//if $label is a datanode, parse out interrnal opts & important pieces
			if ($this->nodeisa($label) == 'datanode') {
				$labelopts = [];
				foreach([
					'wrap', //<label>$text$input</label> or <label>$text</label>$input? *
					'labelafter', // or $input<label>$text</label> ? * combine for <label>$input$text</label>
					'noblanks', //skip empty <label></label> if true; default FALSE
					'tag', 'attr' //same as node expects; we intercept
				] AS $opt) {
					if (isset($label[$opt])) {
						$labelopts[$opt] = $label[$opt];
						unset($label[$opt]);
					}
				}

				//if we got an array but tag was not set, default it
				if (!isset($labelopts['tag'])) {
					$labelopts['tag'] = true;
				}
			}

			//now nodify whatever we've got left
			$content = $this->nodebyref($label);


			if (isset($labelopts) && $labelopts['tag'] &&
				!(empty($content) && (isset($labelopts['noblanks']) && ($labelopts['noblanks'] == true)))
			) {
				//set 'true' to use default 'label' tag, or pass to override
				$labelopts['tag'] = ($labelopts['tag'] === true ? 'label' : $labelopts['tag']);

				$label_attr = (!empty($labelopts['attr'])) ? array_merge($labelopts['attr'],$label) : $label;

				if (!empty($labelopts['wrap'])) {
					//<label>$input$text</label>
					if ($labelopts['labelafter']) {
						$c = $html.$content;
					}
					//<label>$text$input</label>
					else {
						$c = $content.$html;
					}
					$html = $this->node([
						'tag' => $labelopts['tag'], 'attr' => $label_attr, 'content' => $c
					],$nodelist,$opts);
				}
				else {
					$labelhtml = $this->node([
						'tag' => $labelopts['tag'], 'attr' => $label_attr, 'content' => $content
					],$nodelist,$opts);
					// $input<label>$text</label>
					if (!empty($labelopts['labelafter'])) {
						$html = $html.$labelhtml;
					}
					// <label>$text</label>$input
					else {
						$html = $labelhtml.$html;
					}
				}
			}
			// or just $text$input
			else {
				$html = $content.$html;
			}
		}

		//use 'nodelist' generally. 'children' & 'menu' are older aliases used in menulists;
		//'selectlist','checkboxlist','radiolist' are aliases for specialized input lists
		$childlist = ''; $children = null;
		foreach ([
			'nodelist','children','menu',
			'selectlist','checkboxlist','radiolist'
		] AS $l) {
			if (isset($node[$l]) && !empty($node[$l]) && $this->valid_nodelist($node[$l])) {
				$children = $node[$l];
				break;
			}
		}

		if (!empty($children)) {
			$childopts = [
				'tag'        => isset($opts['tag']) ? $opts['tag'] : null,
				'list_class' =>
					(isset($node['child_class']) ? $node['child_class'] :
						(isset($opts['child_class']) ? $opts['child_class'] :
							(isset($opts['list_class']) ? $opts['list_class'] : null)
						)
					)
			];
			$childlist = $this->nodelist($children,$childopts);
			$html .= $childlist;
			unset($node['nodelist']); unset($node['children']);
		}

		return $html;
	}


	/*
		NODELISTS: nodelist(), nodelistbyref(), _dive_to_youngest()
	*/

	/*
		generate a list of nodes from an array. accepts:

		$nodelist = $nodes
			generates a raw list:
				item1.item2


		$nodelist = [ 'tag' => 'ul|ol|dl|table', 'nodes' => $nodes ]

			generates a list entity:
				if ul|ol|table
					<ul><li>node1</li><li>node2</li></ul>
					<ol><li>node1</li><li>node2</li></ol>
					<table><tr><td>node1</td></tr><tr><td>node2</td></tr></table>

				if dl, default will be:
					<dl><dt>node1</dt><dd></dd><dt>node2</dt><dd></dd></dl>


					which probably doesn't make much sense.
					hence you can do:

		$nodelist = [
			'tag'   => ['dl' => ['splitonlabel' => true]],
			'nodes' => [$node_with_label,$node_without_label]
		]
				where $node_with_label probably looks like:
				   ['label' => $label, %content]
				and $node_without_label is anything that is not
				an array with a 'label' key;

			which generates:
				<dl>
					<dt>node1-label</dt><dd>node1</dd>
					<dt></dt><dd>node2</dd>
				</dl>


				the same tag-option technique works with tables:

		$nodelist = [
			'tag'   => ['table' => ['splitonlabel' => true]],
			'nodes' => [$node_with_label,$node_without_label]
		]

			generates:
				<table>
					<tr><td>node1-label</td><td>node1</td></tr>
					<tr><td></td><td>node2</td></tr>
				</table>


				while the 'breakon' option:

		$nodelist = [
			'tag'   => ['table' => ['breakon' => 'td']],
			'nodes' => [$node_with_label,$node_without_label]
		]

			generates one row with a node per cell:
			("break on td (instead of tr)")

				<table>
					<tr>
						<td>node1</td><td>node2</td>
					</tr>
				</table>


			combining them, we get:

		$nodelist = [
			'tag'   => ['table' => ['breakon' => 'td','spliton_label'=>true]],
			'nodes' => [$node_with_label,$node_without_label]
		]
				<table>
					<tr>
						<td>node1-label</td><td>node1</td>
						<td>node2-label</td><td>node2</td>
					</tr>
				</table>


	*/

	/* determine if nodelist=[nodes=$n] or nodelist=[$n] */
	public function nodelisttype($nodelist) {
		/* if array has string keys, assume it's the form nodelist=[nodes=$n] (whether
			or not nodes key found) otherwise assume it's the form nodelist=$n */
		if (\BF\Utils::isArrayHash($nodelist)) {
			return 'nodelist';
		}
		return 'nodes';
	}

	/* if 'nodes' type, assume valid. if 'nodelist' type, valid if has nodes  */
	public function valid_nodelist($nodelist) {
		if (!is_array($nodelist)) return false;
		if ($this->nodelisttype($nodelist) == 'nodes') return true;
		if (isset($nodelist['nodes'])) return true;
		return false;
	}

	private static $_shiftyopts = ['item_attr','node_attr','node_opts'];
	private static $_shiftyopt_nodekeys = [
		'item_attr' => 'item_attr', 'node_attr' => 'attr', 'node_opt' => null
	];
	public function nodelist($nodelist,$opts=[]) {
		return $this->nodelistbyref($nodelist,$opts);
	}
	public function nodelistbyref(&$nodelist,$opts=[]) {
		if (!is_array($nodelist)) return $nodelist;

		$nodes = $tag = $tagopts = $attr = null;
		$html  = '';

		$mode = $this->nodelisttype($nodelist);
		if ($mode == 'nodelist') {
			if (!isset($nodelist['nodes'])) {
				return; // must send nodes. send nodes=[] if you want an empty list
			}
			if (isset($nodelist['nodes'])) { $nodes = $nodelist['nodes']; unset($nodelist['nodes']); }
			if (isset($nodelist['tag']))   { $tag   = $nodelist['tag'];   unset($nodelist['tag']);   }
			if (isset($nodelist['attr']))  { $attr  = $nodelist['attr'];  unset($nodelist['attr']);  }
		}
		else {
			$nodes = $nodelist;
			$nodelist=[];
		}

		if (isset($opts['list_class'])) {//legacy opt
			$attr['class'] = $opts['list_class'];
		}
		//nodelist 'tag' and 'tagopts'
			/*
				#FIXME nodelist[tag] no longer has much to do with the tag. and
				it's easy to confuse with node[tag], which is related but not
				equivalent. so rename this one. should be something clearer, like
				'listopts'. probably separate to multiple things, with node[tag] 
				related things staying on nodelist[tag] (and re-merging where 
				appropriate). I think originally the idea was, put listopt things 
				here because they only applied if tag was set. but now we can set 
				tag=false, where we actually don't print a tag ([notag]<div1><div2>[/notag]), 
				but still want to use listopts. I may have hacked around that elsewhere
				/FIXME
			*/
		if (!isset($tag) && !empty($opts['tag'])) {
			$tag = $opts['tag'];
		}
		if (!isset($tag) && ($mode == 'nodelist')) {
			//default ONLY if nodelist == [nodes=$n] and no opt;
			//not when nodelist == [$n], which assumes no default

			$tag = 'ul';
		}
		if (is_array($tag)) {

			//first extract attr
			if (isset($tag['attr'])) {
				$attr = isset($attr) ? array_merge($attr,$tag['attr']) : $tag['attr'];
				unset($tag['attr']);
			}

			// $tag == [ 'tag' => 'table', item_attr => $ia] -- turn into
			// $tag == [ 'table' => [item_attr => $ia]], OR
			// $tag == ['tag' => false, 'childtag' => $ct, item_attr => $ia] -- turn into
			// $tag == false, $tagopts == ['childtag' => $ct, item_attr => $ia]
			if (isset($tag['tag'])) {
				if ($tag['tag'] == true) {
					if ($tag['tag'] === true) {
						$thetag = true;
					}
					else {
						if (is_array($tag['tag'])) {
							//recursive merging may have created an array, when
							//we really wanted an override. take the last one added
							//that is an actual string (eg skip 'true')
							foreach (array_reverse($tag['tag']) AS $t) {
								if (is_string($t)) {
									$tag['tag'] = $t; break;
								}
							}
						}
						$thetag = $tag['tag'];
						$tag = [ $thetag => $tag ];
					}
				}
				else {
					$tagopts = $tag;
					$tag = false;
				}
				unset ($tag['tag']);
			}

			if ($tag && !$tagopts) {
				// $tag == [ 'table' => $def] -- turn array of one into
				// $tag == 'table', $tagopts = $def
				$tagopts = $tag[key($tag)]; $tag = key($tag);
			}

			/*
				tagopts may include options: see 'breakon' and 'spliton_*' options in descrip;
				may also include attributes (for eg, the 'ul' tag):
			*/

			//FIXME? can this still happen or did we already extract above?
			//should we always do it here or there or both?
			if (isset($tagopts['attr'])) {
				$attr = isset($attr) ? array_merge($attr,$tagopts['attr']) : $tagopts['attr'];
				unset($tagopts['attr']);
			}

			/*** SHIFTY tagopts: item_attr, node_attr, node_opts
				these three can be an array, or an array of arrays;
				if array, use for each node. if array of arrays,
				shift and eat one off the array for each node

				item_attr: attributes for the listitem-enclosing tag (eg, the 'li' tags)
				node_attr: attributes (attr key) for the [node]. may have
					differing effects depending on what else is in the node
					(eg, 'attr' may go with 'label', or 'tag', etc -- see SLUSHY attributes)
				node_opts: options for the [node] -- keys are merged with [node];
					may use to add labels, change content, etc
			*/
			$shiftyopts = null;
			foreach(self::$_shiftyopts AS $opt) {
				if (isset($tagopts[$opt])) {
					//if array has string keys, assume it's a single attr array; goes to all children
					if (count(array_filter(array_keys($tagopts[$opt]), 'is_string'))>0)
						$shiftyopts[$opt] = $tagopts[$opt];
					unset($tagopts[$opt]);
				}
			}

			/* and optionally may contain "alt_construct" segments;
				this can tell us to parse a table into thead,tbody,tfoot
				instead of just rows:
			*/
			if ($tag && ($alt_constructs = $this->_alt_constructs_for($tag))) {
				foreach($alt_constructs AS $c => $altcon) {
					//each construct is an array of one, eg ['thead' => $def]
					$cname = key($altcon); $def = $altcon[$cname];

					if (isset($tagopts[$cname])) {
						$c_nodelist = [];

						$childtagopts = $ac_attr = null;
						if (is_array($tagopts[$cname])) {

							//first extract any attr
							if (isset($tagopts[$cname]['attr'])) {
								$ac_attr = $tagopts[$cname]['attr'];
								unset($tagopts[$cname]['attr']);
							}

							//now look for instructive keys
							if (isset($tagopts[$cname]['nodes'])) {
								//can just pass a separate list of nodes
								$c_nodelist = $tagopts[$cname]['nodes'];
								unset($tagopts[$cname]['nodes']);
							}
							elseif (isset($tagopts[$cname]['splice'])) {
								// or pass params [splice=>[offset,length]],
								// which tells us how to splice off the main list
								$spliceargs = $tagopts[$cname]['splice'];
								if (isset($spliceargs[0])) {
									$arg1 = (is_int($spliceargs[0]) || ctype_digit($spliceargs[0])) ? $spliceargs[0] : 0;
									if (isset($spliceargs[1])) {
										$arg2 = (is_int($spliceargs[1]) || ctype_digit($spliceargs[1])) ? $spliceargs[1] : 0;
										$c_nodelist = array_splice($nodes,$arg1,$arg2);
									}
									else {
										$c_nodelist = array_splice($nodes,$arg1);
									}
								}
								unset($tagopts[$cname]['splice']);
							}

							//anything left are opts for the child tag, eg breakon, etc
							$childtagopts = $tagopts[$cname];
						}
						//can just set eg, tbody=true to use all $nodelist
						//(or whatever's left after slicing off the header)
						//(of course that doesn't work if you have a footer)
						elseif ($tagopts[$cname] === true) {
							$c_nodelist = $nodes;
							$nodes = [];
						}
						unset($tagopts[$cname]);

						$childtagopts['childtag'] = $def;

						//clone the main tagopts array; remove any other
						//alt construct tags
						$clonetagopts = $tagopts;
						foreach ($this->_alt_constructs_for($tag) as $c => $v) {
							$k = key($v);
							if (isset($clonetagopts[$k])) {
								unset($clonetagopts[$k]);
							}
						}

						foreach($clonetagopts AS $k=>$v) {
							if (!array_key_exists($k,$childtagopts)) {
								$childtagopts[$k] = $v;
							}
						}

						$thishtml = $this->nodelist([
							'tag' => [$cname => $childtagopts], 'attr' => $ac_attr, 'nodes' => $c_nodelist
						],$tagopts);

						$html .= $thishtml;
					}
				}
			}
		}


		$rows = [];
		foreach ($nodes AS $node) {

			//can set a preprocess key which is a callback function to do one final
			//manipulation on each datanode before diving
			$preprocess = false;
			if ($this->nodeisa($node) == 'datanode') {
				if (isset($tagopts['preprocess']) && is_callable($tagopts['preprocess'])) {
					$preprocess = $tagopts['preprocess'];
				}
			}

			//can set a postprocess key which is a callback function to do one final
			//manipulation on each datanode before generating the string
			$postprocess = false;
			if ($this->nodeisa($node) == 'datanode') {
				if (isset($tagopts['postprocess']) && is_callable($tagopts['postprocess'])) {
					$postprocess = $tagopts['postprocess'];
				}
			}

			/* SHIFTY opts: item_attr, node_attr, node_opts
				-- apply to node
			 */
			foreach (self::$_shiftyopts AS $opt) {

				$def = null;
				if (isset($shiftyopts[$opt])) {
					// we had one for all nodes and claimed it
					$def = $shiftyopts[$opt];
				}
				elseif (isset($tagopts[$opt])) {
					// it is set but we didn't claim it -- must
					// have been array of arrays. eat one.
					$def = array_shift($tagopts[$opt]);
				}
				if ($def) {
					/*	we are adding these things to the node itself, so they skate
						from here down through kids to the grandkid; meaning, eg,
						item_attr affects the td, not the tr. not sure I have a way to 
						send attr to the tr yet. Maybe I did it once, using the array 
						eating technique above. Though that was originally for something else */

					if ($this->nodeisa($node) == 'datanode') {
						if (array_key_exists($opt,self::$_shiftyopt_nodekeys) &&
							($key = self::$_shiftyopt_nodekeys[$opt])
						) {
							$node[$key] = isset($node[$key]) ?
								array_merge_recursive($def,$node[$key]) : $def;
						}
						else {
							$node = array_merge_recursive($def,$node);
						}
					}
					else {
						$def['content'] = $node;
						$node = $def;
					}
				}
			}

			if ($preprocess) {
				$node = $preprocess($node,$nodelist);
			}

			if (isset($tag) && (
				($tag == true) || (isset($tagopts['childtag']))
			)) {
				//make a copy of main node that dive can eat
				$noderef = $node; $thisref = $this;

				$callback =	function(&$node,$cbTag = null,$content=null) use ($thisref,$nodelist,$opts,$postprocess) {
					//node could be empty because it could have been eaten already;
					//presumably in that case, we received $content
					if (isset($node) && !isset($content)) {
						if ($postprocess) {
							$node = call_user_func($postprocess,$node,$nodelist);
						}
						$content = $thisref->nodebyref($node,$nodelist,$opts);
					}
					elseif (!isset($content)) {
						return;
					}

					$retnode = ['content' => $content];
					if (isset($cbTag)) {
						$retnode['tag']  = $cbTag;
					}
					if (isset($node)) {
						$item_attr = null;
						if (!empty($node['item_attr'])) {
							$item_attr = $node['item_attr'];
						}
						if (!empty($node['item_class'])) {
							$node['item_attr']['class'] = $node['item_class'];
						}
						$retnode['attr'] = $item_attr;
					}
					return $thisref->node($retnode,$nodelist,$opts);
				};

				$item = $this->_dive_to_youngest($noderef,$callback,$tag,$tagopts);
				if (!$item && $item !== '') {
					$childtag = isset($tagopts['childtag']) ?
						$tagopts['childtag'] : $this->_child_tag_for($tag);
					if ($childtag) {
						if (!is_array($childtag)) $childtag = [$childtag];
						if ($splitchild = $this->_spliton($noderef,$childtag,$callback,$tagopts)) {
							$item = $splitchild;
						}
					}
				}
			}
			else {
				if ($postprocess) {
					$node = call_user_func($postprocess,$node,$nodelist);
				}
				$item = $this->nodebyref($node,$nodelist,$opts);
			}
			$rows[] = $item;
		}

		$html .= implode("\n",$rows);

		if ($tag) {
			// if we have any expecting parents (dive had to skip them because
			// of the breakon level specified), wrap content in them now
			if ($parents = $this->_expecting_parents) {
				foreach ($parents AS $ptag) {
					$html = $this->node(['tag' => $ptag, 'content' => $html],$nodelist,$opts);
				}
			}

			$node = ['tag' => $tag, 'attr' => $attr, 'content' => $html];
			if ($mode == 'nodelist') {
				$node = array_merge($nodelist,$node);
			}

			//wrap in a parent node if so instructed
			if (isset($tagopts['wrap'])) {
				$wrap = $tagopts['wrap']; unset($tagopts['wrap']);
				$wrap['content'] = $this->node($node,$nodelist,$opts);
				return $this->node($wrap);
			}
			return $this->node($node,$nodelist,$opts);
		}
		if (count($nodelist) && ($mode == 'nodelist')) {
			$nodelist['content'] = $html;
			return $this->node($nodelist,[],$opts);
		}

		return $html;
	}

	/*	dives through child_tags_for with callbacks;
			allows us to push content down to the deepest member,
			and build parent rows above according to spec
	*/
	private $_expecting_parents = null;
	private function _dive_to_youngest(&$node,$callback,$parenttag,$opts=[]) {

		if (isset($opts['diving'])) {
			if (!isset($opts['breakon'])) $opts['breakon'] = $parenttag;
		}
		else {
			//initial call
			$opts['diving'] = 0;
			$this->_expecting_parents = [];
		}
		$etabs = str_repeat('  ',$opts['diving']);

		$childtag = isset($opts['childtag']) ?
			$opts['childtag'] : $this->_child_tag_for($parenttag);

		if (is_array($childtag)) {

			if (!isset($opts['_breakon_level']))   $opts['_breakon_level'] = 0;
			if (!(isset($opts['breakon']) && ($opts['breakon'] == $parenttag))) $opts['_breakon_level']++;

			$children = [];
			foreach ($childtag AS $i => $grandchildtag) {

				$childopts = array_merge($opts,[
					'diving' => $opts['diving']+1,
					'childtag' => $grandchildtag,
				]);

				$abandon_childtags = false;
				$child = $this->_dive_to_youngest($node,$callback,$i,$childopts);
				if (!$child && $child !== '') {
					if ($splitchild = $this->_spliton($node,$childtag,$callback,$opts)) {
						$child = $splitchild;
						$abandon_childtags = true;
					}
				}

				if ($opts['diving'] && !($opts['breakon'] == $parenttag)) {
					//we're not the parent call and not breaking here
					
					if ($opts['diving'] < $opts['_breakon_level']) {
						// we have bubbled up past breakon,
						// so add remaining parents to expecting_parents
						$this->_expecting_parents[] = $parenttag;
					}
					else {
						// we haven't bubbled up to breakon yet, so
						// wrap child with current parent
						$child = $callback($node,$parenttag,['content' => $child]);
					}
				}
				$children[] = $child;

				if ($abandon_childtags) {
					break;
				}
			}

			if ($opts['diving']) {
				if ($opts['breakon'] == $parenttag) {
					//we're breaking now, so add the breaking parent around all the children
					return $callback($node,$parenttag,['nodelist' => $children]);
				}
			}

			return $callback($node,null,['nodelist' => $children]);
		}

		//childtag is string branch (eg, we finally hit 'td', not array ['td'])
		else {
			foreach (self::$spliton_segs AS $seg) {
				if (isset($opts['spliton_'.$seg]) && ($opts['spliton_'.$seg] == true) &&
					//can set spliton_*=whenfound -- don't split if segment not found.
					(isset($node[$seg]) || ($opts['spliton_'.$seg] !== 'whenfound'))
				) {
					// by returning false we'll force parent call to evaluate
					// options and split the node accordingly
					return false;
				}
			}

			//check for alternate 'final' tags, eg td|th
			$childtag_alts = explode('|',$childtag);
			$usechildtag = $childtag_alts[0]; //first is default

			//tag options can include 'tagswap' key, eg ['tagswap'=['td' => 'th'], which says,
			//if I'm going to render a td, render a th instead.
			$tagswap = null;
			if (isset($opts['tagswap']) && isset($opts['tagswap'][$usechildtag])) {
				$tagswap = $opts['tagswap'][$usechildtag];
			}
			//node can also include 'tagswap' key, which in turn says,
			//I'm going to be rendered in a td, render me in a th instead.
			//If both set, node overrides
			if (isset($node['tagswap']) && isset($node['tagswap'][$usechildtag])) {
				$tagswap = $node['tagswap'][$usechildtag];
				unset($node['tagswap'][$usechildtag]);
			}
			//Will only register if we acknowledge th as a valid alternative for td.
			if (isset($tagswap)) {
				foreach($childtag_alts AS $alt) {
					if ($alt == $tagswap) $usechildtag = $alt;
				}
			}

			$content = $callback($node,$usechildtag);
			return isset($content) ? $content : '';
		}
	}

	/* handle 'spliton_*' options which will split a node into
		major segments (label,before,node,after,nodelist) which
		to populate multiple childtags; eg, split label into dt,
		rest of node into dd, etc.
	 */
	private static $spliton_segs  =  ['label','before','after','nodelist'];
	private static $all_node_segs =  ['label','before','node','after','nodelist'];
	private function _spliton(&$node,$childtag,$callback,$opts) {

		//if we didn't dive to at least an empty string,
		//we must have returned on purpose from the string branch

		// optionally split node into major segments, to populate
		// multiple grandchild tags at once
		$nodechunks = null;
		foreach(self::$spliton_segs AS $seg) {
			if (isset($opts['spliton_'.$seg])) {
				if (isset($node[$seg])) {
					//turn seg into node-with-seg
					$nodechunks[$seg] = [$seg => $node[$seg]];
					unset($node[$seg]);
				}
				else {
					$nodechunks[$seg] = [];
				}
			}
		}
		if (!isset($nodechunks)) return false;

		$nodechunks['node'] = $node;
		/* idea is:
			if $childtag=dl, get grandkids 'dt','dd'
			if $childtag=tr, get grandkids 'td','td',... [as needed]

			i may have one to five (so far) chunks;
			fill out those grandkids with those chunks as we can
		*/

		//take only as many of the grandchildtags as I can fill
		$numchunks = count($nodechunks);
		$childtags = array_slice($childtag,0,$numchunks);

		//if there is only one grandchild tag,
		//take it as many times as we have chunks
		$numctags  = count($childtags);
		if ($numctags == 1) {
			for($i=1;$i<$numchunks;$i++) {
				$childtags[] = $childtags[0];
			}
			$numctags = $numchunks;
		}
		elseif ($numctags < $numchunks) {
			//I have too many chunks. eg, we're trying to fill a dt,dd
			//but we have 3 or more chunks. You split on too many things!
			// try to sanely recombine?
			// note: numctags should be >= 2, because if it was 1, we
			// would have multiplied it out to numchunks above.
			// if numctags >=2, numchunks must be >= 3

			$newchunks = [];$tempctags = $numctags;
			if ($tempctags && isset($nodechunks['label'])) {
				$newchunks['label'] = $nodechunks['label'];
				$tempctags--; unset($nodechunks['label']);
			}
			$needed = $tempctags - $numctags;
			if ($needed == 2) {
				// i still have two tags left to fill.
				// if I have a nodelist, take it for the second tag
				if (isset($nodechunks['nodelist'])) {
					$newchunks['nodelist'] = $nodechunks['nodelist'];
					unset($nodechunks['nodelist']);
				}
				elseif (!isset($newchunks['label'])) {
					//we probably have a dt,dd, and didn't get a label, and
					//we probably want everything in the dd. so set an empty
					//label to fill the dt and let everything else recombine
					$newchunks['label'] = [];
				}
			}
			// if needed=1, fall through to just build node; if needed>2, we are
			// in a hypothetical world that doesn't yet exist, so I don't care
			$newchunks['node'] = $nodechunks['node'];
			unset($nodechunks['node']);
			foreach ($nodechunks AS $s=>$chunk) {
				$newchunks['node'][$s] = $chunk;
			}
			$nodechunks = $newchunks;
		}

		/* if node has item_attr & it's an array by segment, shift the items
			off to merge with the segment item attr.
			if not an array, assume it goes with the main node.
		*/
		if (isset($node['item_attr'])) {
			$foundone = false;
			foreach($nodechunks AS $seg => $chunk) {
				if (isset($node['item_attr'][$seg])) {
					$foudnone=true;
					$chunkattr = $node['item_attr'][$seg];
					$nodechunks[$seg]['item_attr'] =
						(($seg != 'node') && isset($nodechunks[$seg]['item_attr'])) ?
							array_merge($nodechunks[$seg]['item_attr'],$chunkattr) : $chunkattr;
					unset($node['item_attr'][$seg]);
				}
			}
			//if item_attr still has keys & no segment kyes found,
			//it's just an attr list. apply it to ALL
			if ((!$foundone) && count($node['item_attr'])) {
				foreach($nodechunks AS $seg => $chunk) {
					$nodechunks[$seg]['item_attr'] = isset($nodechunks[$seg]['item_attr']) ?
						array_merge($nodechunks[$seg]['item_attr'],$node['item_attr']) : $node['item_attr'];
				}
			}
			else {
				unset($node['item_attr']);
			}
		}

		//now callback foreach segment to build $child
		$child = '';
		foreach(self::$all_node_segs AS $seg) {
			if (isset($nodechunks[$seg])) {
				if ($ct = array_shift($childtags)) {

					//check for alternate 'final' tags, eg td|th
					$childtag_alts = explode('|',$ct);
					$usechildtag = $childtag_alts[0];

					$thischild = $callback($nodechunks[$seg],$usechildtag);
					$child.=$thischild;
				} //else should not happen since we normalized tagcount-to-chunkcount
			}
		}
		return $child;
	}
}
?>
