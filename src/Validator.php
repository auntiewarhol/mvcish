<?php
namespace awPHP\MVCish;
use Symfony\Polyfill\Intl\Idn;


class Validator extends \awPHP\MVCish\Base {

	private array $_validators;
	private array $_defaulters;

	public function __construct(\awPHP\MVCish\MVCish $MVCish) {
		parent::__construct($MVCish);

		// set these in construction so that $this is available to funcs who need it

		$this->_validators = [
			'boolean' => function($validator,&$value) {
				// if $answer is "1", "true", "on", "yes", "0", "false", "off", "no", or ""
				// return true and convert $answer to true boolean
				$res = filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
				if ($res === NULL) return false;
				$value = $res;
				return true;
			},
			'digit' => function($validator,$value) {
				return ctype_digit($value);
			},
			'alphanumeric' => function($validator,$value) {
				return ctype_alnum($value);
			},
			// alphanumeric plus underscore
			'word' => function($validator,$value) {
				return preg_match('/^\w*$/',$value) ? true : false;
			},
			// word plus hyphen
			'word-plus' => function($validator,$value) {
				return preg_match('/^[\w-]*$/',$value) ? true : false;
			},
			'min' => function($validator,$value,$min) {
				if ($value >= $min) return true;
				return 'Please enter a value >= '.$min;
			},
			'max' => function($validator,$value,$max) {
				if ($value <= $max) return true;
				return 'Please enter a value <= '.$max;
			},
			'email' => function($validator,$value) {
				if (filter_var($value,FILTER_VALIDATE_EMAIL) === false) {
					return 'Please enter a valid email';
				}
				return true;
			},
			'domain' => function($validator,$value) {
				if (filter_var($value,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME) === false) {
					return 'Please enter a valid domain';
				}
				return true;
			},
			// by default validates http & https only. pass allowedSchemes to validate others
			'uri' => function($validator,&$value,$options=[]) {
				// options:
				if (!isset($options['allowedSchemes']))  $options['allowedSchemes'] = ['http','https'];
				if (!isset($options['allowUnicode']))    $options['allowUnicode'] = true;
				if (!isset($options['allowPrivate']))    $options['allowPrivate'] = false;
				if (!isset($options['allowIP']))         $options['allowIP']      = true;
				$modvalue = $value;

				// first, filter/sanitize it
				$filtered = filter_var($modvalue,FILTER_VALIDATE_URL);
				if ($filtered === false) {
					// try adding http, if it's in the allowed schemes and not
					// already there, then pass it through filter_var again
					if (in_array('http',$options['allowedSchemes']) && 
						!preg_match('/^[a-z]*:/',$modvalue)
					) {
						// ltrim in case we got a schemeles //foo.bar
						$modvalue = 'http://'.ltrim($modvalue,'/');
						$filtered = filter_var($modvalue,FILTER_VALIDATE_URL);
					}
				}

				// filter_var will not allow unicode domains. so we don't care
				// whether it passed or failed, we're just letting it sanitize.
				if ($filtered === false) {
					// filter_var will fail unicode domains. so if filter_var
					// failed and we don't want unicode, fail now.
					if (!$options['allowUnicode'])
						return 'Please enter a valid URL (unicode not allowed).';
				}
				else {
					// take any sanitization filter_var may have done
					$modvalue = $filtered;
				}

				// now test allowed schemes
				$schemeOK = false;
				foreach ($options['allowedSchemes'] as $scheme) {
					if (strpos($modvalue,$scheme.'://') === 0) {
						$schemeOK = true; break;
					}
				}
				if (!$schemeOK) {
					return 'Please enter a valid '
						.$this->listifyArray($options['allowedSchemes'],'or').' URL.';
				}

				// we're good now if allowing private domains & filter_var passed
				if ($options['allowPrivate'] && ($filtered !== false)) {
					$value = $modvalue;
					return true;
				}

				$parsedHost = parse_url($modvalue, PHP_URL_HOST);

				$parser = $this->MVCish()->domainParser();
				$result = $parser->resolvePublicSuffixList($parsedHost);
				if ($result->suffix()->isICANN()) {

					if ($filtered !== false ) {
						// filter_var said it was ok, so ok
						$value = $modvalue;
						return true;
					}
					elseif ($options['allowUnicode']) {
						$encoded = idn_to_ascii($parsedHost);

						// an ascii hostname should only contain letters, numbers, or .-_
						// if punycode didn't encode to that, it should be a fail.
						if (preg_match('/^[a-zA-Z0-9_.\/-]*$/',$encoded)) {
							$value = $modvalue;
							return true;
						}
					}
				}
				elseif ($options['allowIP'] && $result->isIp()) {
					$value = $modvalue;
					return true;
				}
				return 'Please enter a valid URL.';
			},
			'minlength' => function($validator,$value,$min) {
				if (mb_strlen($value) < $min) {
					return "Please enter a minimum $min characters.";
				}
				return true;
			},
			'maxlength' => function($validator,$value,$max) {
				if (mb_strlen($value) > $max) {
					return "Please enter a maximum $max characters.";
				}
				return true;
			},
			'date_iso' => function($validator,$value) {
				if (preg_match("/^(\d\d\d\d)\-(\d\d)\-(\d\d)$/",$value,$matches)) {
					if (checkdate($matches[2],$matches[3],$matches[1])) {
						return true;
					}
				}
				return false;
			},
			//convert date from $format, validate & return as iso
			'date_format_to_iso' => function($validator,&$value,$format) {
				if ($isoDate = \DateTime::createFromFormat($format,$value)) {
					$value = $isoDate->format('Y-m-d');
					if (preg_match("/^(\d\d\d\d)\-(\d\d)\-(\d\d)$/",$value,$matches)) {
						if (checkdate($matches[2],$matches[3],$matches[1])) {
							return true;
						}
					}
				}
				return false;
			}
		];
		$this->_defaulters = [
			'booltrue' => function($validator,$value) {
				return //return true unless (0,false,off,no,"")
					(filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE) === false) ? false : true;
			},
			'boolfalse' => function($validator,$value) {
				return //return false unless (1,true,on,yes)
					(filter_var($value,FILTER_VALIDATE_BOOLEAN) == true) ? true : false;
			},
			'intbooltrue' => function($validator,$value) {
				return //return 1 unless (0,false,off,no,"")
					(filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE) === false) ? 0 : 1;
			},
			'intboolfalse' => function($validator,$value) {
				return //return 0 unless (1,true,on,yes)
					(filter_var($value,FILTER_VALIDATE_BOOLEAN) == true) ? 1 : 0;
			},
		];
	}

	//****************************************************************************
	/* pass no args, get the full functions hash;
		pass one arg, get the validator/defaulter of that name;
		pass two args, process arg2 using the arg1 function
	*/
	public function validators():mixed {
		$args = func_get_args();
		if (isset($args[0])) {
			$validator = isset($this->_validators[$args[0]]) ? $this->_validators[$args[0]] : false;
			if ($validator && isset($args[1])) return $validator($this,$args[1]);
			return $validator;
		}
		return $this->_validators;
	}

	public function defaulters():mixed {
		$args = func_get_args();
		if (isset($args[0])) {
			$defaulter = isset($this->_defaulters[$args[0]]) ? $this->_defaulters[$args[0]] : false;
			if ($defaulter && isset($args[1])) return $defaulter($this,$args[1]);
			return $defaulter;
		}
		return $this->_defaulters;
	}


	//****************************************************************************
	private $_passed_data = null;
	private $_cleaned_data  = [];

	// use 'data' within a validator to extract other fields
	// in the form definition (so we don't have to clean more
	// than once), or just generally if you want to extract
	// a value form POST or a passed $src.

	public function data($field,$default=false,$src=false,$cache=true) {
		if ((!isset($this->_cleaned_data[$field])) || (!$cache)) {
			if (isset($src) && is_array($src)) {
				$val = isset($src[$field]) ? $src[$field] : null;
			}
			else {
				$val = isset($_POST[$field]) ? $_POST[$field] : null;
			}

			$cleaned = $this->cleanData($val,$default);
			if ($cache) {
				$this->_cleaned_data[$field] = $cleaned;
			}
			else {
				return $cleaned;
			}
		}
		return $this->_cleaned_data[$field];
	}


	// use 'cleanData' to just clean a value.

	public function cleanData($val,$default=false,$options=[]) {


		if (!isset($options['strip_tags'])) $options['strip_tags'] = true;
		/* legacy benchfly code always used strip_tags. But apparently
			it isn't smart enough to understand text like "dogs < cats";
			so if you need lt/gt chars in a field, you can optionally turn it off.
			I considered changing to using htmlspecialchars, but that is
			better used on output than on input (better to store the data as-is)
		*/

		if (is_array($val)) {
			foreach ($val AS $i=>$v) {
				$val[$i] = $this->cleanData($v,$default,$options);
			}
		}
		else {
			if (is_string($val)) {
				if ($options['strip_tags']) $val = strip_tags($val);
				$val = trim($val);
			}
			elseif (!(is_bool($val) || is_numeric($val))) {
				$val = false; //only array,string,numbers,bool allowed
			}
			//allow 0, but treat '' as null/false
			if (empty($val) && $val !== 0 && $val !== '0') {
				$val = $default;
			}
		}
		return $val;
	}


	//****************************************************************************
	//****************************************************************************

	public function validateForm(array $definition,array $source=null,bool $cache=true):array {
		$response = ['success'=>true];
		foreach($definition AS $field => $def) {

			if (isset($def['source'])) {
				$val = $this->data($field,false,$def['source'],$cache);
			}
			elseif ($source) {
				$val = $this->data($field,false,$source,$cache);
			}
			else {
				$val = $this->data($field,false,false,$cache);
			}
			$name = isset($def['name']) ? $def['name'] : $field;

			$defaulted = $validated = false;
			if (isset($def['validate_array'])) {
				if (is_array($val)) {
					if ($profile = $def['validate_array']['profile']) {
						foreach ($val AS $k=>$v) {
							$i = is_int($k) ? ($k+1) : $k;

							$subresp = null;
							$subresp = $this->validateForm($profile,$v,false);

							if (!$subresp['success']) {
								$response['success'] = false;
								if ($subresp['error']) {
									foreach($subresp['error'] AS $f => $m) {
										$msg = $name."[$i]: ".$m;

										$response['error'][$field][$k][$f] = $msg;
										$response['messages']['error'][] = $msg;
									}
								}
								else {
									$msg = $name."[$i]: validation failed";
									$response['error'][$field][$k] = $msg;
									$response['messages']['error'][] = $msg;
								}
							}
						}
					}
				}
				elseif(!empty($val)) {
					$response['success'] = false;
					$response['error'][$field] = $name.": Invalid data format";
				}
			}
			elseif (isset($def['defaulter'])) {

				// a 'defaulter' will accept or set a value regardless of
				// what, if anything, was sent. No other operations will apply.
				// good for things like 'if sent anything but true, set false'

				if (is_callable($def['defaulter'])) {
					$defaulter = $def['defaulter'];
				}
				elseif ($v = $this->defaulters($def['defaulter'])) {
					$defaulter = $v;
				}
				if (isset($defaulter)) {
					$defaulted = true;
					$val = $defaulter($this,$val);
				}
				else {
					error_log("error in validate_form: can't grok defaulter from ".$def['defaulter']);
				}
			}
			//extraction should have set null,false,and '' (but not 0 or '0') to explicit false
			elseif (($val === false) && !(isset($def['valid']) && isset($def['validateEmpty']))) {

				// a 'default' sets a value only if empty
				// can set default='' or default=null when you want to accept
				// empty as valid instead of ignored (for clearing a field)

				// push($phpsucks,"'NULL' is a value. If something is set to null, it is set!")
				//if(isset($def['default'])) 
				if(array_key_exists('default',$def)) {
					if (is_callable($def['default'])) {
						$val = $def['default']($this,$val);
					}
					else {
						$val = $def['default'];
					}
				}

				//'default' + 'required' wouldn't make much sense
				elseif ((!empty($def['required'])) || (
					array_key_exists('requiredIf',$def) &&
						(is_callable($def['requiredIf']) ? $def['requiredIf']($this) : $def['requiredIf'])
				)) {
					if (array_key_exists('missing',$def)) {
						if (is_callable($def['missing'])) {
							$msg = $def['missing']($this,$val);
						}
						else {
							$msg = $def['missing'];
						}
					}
					else {
						$msg = $name . ' is required.';
					}
					$response['success'] = false;
					$response['error'][$field] = $msg;
					$response['messages']['error'][] = $msg;
					if (!isset($response['missing'])) $response['missing'] = [];
					$response['missing'][] = $field;
				}
			}
			elseif (isset($def['valid'])) {

				// valid ='validator'
				// valid = validatorfunction()
				// valid = ['validator1','validator2',validator3function()]
				// valid = ['validator1'=>$param,'validator2',validator3function()]
				if (!is_array($def['valid'])) {
					$def['valid'] = [$def['valid']];
				}
				foreach ($def['valid'] AS $k=>$v) {
					$validator = $validatorname = $validated = false;
					$param = null;

					if (is_string($k)) {
						$validatorname = $k;
						$param = $v;
					}
					elseif (is_callable($v)) {
						$validator = $v;
					}
					else {
						$validatorname = $v;
					}
					if ($validatorname) {
						if ($v = $this->validators($validatorname)) {
							$validator = $v;
						}
						else {
							error_log("error in validate_form: unknown validator '$validatorname'");
						}
					}
					if ($validator) {
						if (isset($param)) {
							$validated = $validator($this,$val,$param);
						}
						else {
							$validated = $validator($this,$val,$source);
						}
					}

					if ($validated !== true) {
						if (isset($def['defaultIfInvalid']) && array_key_exists('default',$def)) {
							if (is_callable($def['default'])) {
								$val = $def['default']($this,$val);
							}
							else {
								$val = $def['default'];
							}
							$defaulted = true;
						}
						else {
							$msg = isset($def['invalid']) ? $def['invalid'] :
								(is_string($validated) ? $validated : 'Invalid entry for '.$name);

							$response['success'] = false;
							$response['error'][$field] = $msg;
							$response['messages']['error'][] = $msg;
							if (!isset($response['invalid'])) $response['invalid'] = [];
							$response['invalid'][] = $field;
						}
						break; // stop on first fail
					}
				}
			}
			elseif (isset($def['optional'])) {
				//nothing to do, just told to collect it
			}

			if ($defaulted || $validated || ($val !== false)) {
				$response['data'][$field] = $val;
				if (!isset($response['error'][$field])) {
					$response['valid'][$field] = $val;
				}
			}
		}
		return $response;
	}

	//****************************************************************************
	public function Response(array $definition,array $source=null,bool $cache=true):array {
		if ($response = $this->validateForm($definition,$source,$cache)) {
			return Response::cFromArray($this->MVCish(),$response);
		}
	}

}
?>
