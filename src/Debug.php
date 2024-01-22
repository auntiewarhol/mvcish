<?php
namespace AuntieWarhol\MVCish;

class Debug extends \AuntieWarhol\MVCish\Base {

	public static function errorHandler($MVCish,$errno, $errstr, $errfile, $errline) {
		//error_log("error_handler ".self::translatePHPerrCode($errno).' '.$errstr.' '.$errfile.' '.$errline);
		// ignore warnings when @ error suppression operator used
		$er = error_reporting();
		if ($er == 0 || $er == 4437) return true; //4437=php8 hack

		// hack to ignore this warning, because the only way to test if something is serialized
		// is to try and unserialize it. Maybe that should use suppression operator tho?
		if (($errno == E_NOTICE) && (substr($errstr,0,11) == 'unserialize')) return true;

		$logged = false;
		$exception = null; $messages = [];

		try {
			// hacky but not sure how else to do it: if you know you're triggering us with an 
			// exception deliberately, set handlingException so we'll use the one you already have
			// (see "Exception\ServerWarning-trigger()")
			if (isset($GLOBALS['MVCish_handlingException'])) {
				$exception = $GLOBALS['MVCish_handlingException'];
				$GLOBALS['MVCish_handlineException'] = null;
			}
			else {
				$exception = \AuntieWarhol\MVCish\Exception::handlerFactory(
					$MVCish, $errno, $errstr, $errfile, $errline
				);
			}
			$MVCish->logExceptionMessage($exception);
			$logged = true;
		}
		catch(\Throwable $e) {
			$messages[] = "Error creating MVCish\Exception: ".$e->getMessage();

			// old fashioned way. just in case
			$logged = false;
			try {
				if (self::isFatalPHPerrCode($errno)) {
					$exception = new \Exception($errstr);
					$MVCish->logExceptionMessage($exception);
					$logged = true;
				}
			}
			catch(\Throwable $e) {
				$messages[] = "Error creating generic Exception: ".$e->getMessage();
			}
		}
		if (!$logged) { // old fashioned way if all else failed
			$messages = array_merge(
				self::_buildErrorMessages($MVCish,$errno, $errstr, $errfile, $errline,
					debug_backtrace()),
				$messages
			);
			$msgMethod = self::isFatalPHPerrCode($errno) ? 'error' : 'warning';
			try {
				foreach ($messages as $m) {
					$MVCish->log('MVCish')->$msgMethod($m);
				}
			} catch (\Throwable $e) {
				$msg[] = "Additional error encountered writing to MVCish log: ".$e->getMessage();
				foreach ($msg as $m) { error_log($m); }
			}
		}

		if (self::isFatalPHPerrCode($errno)) {
			if (isset($exception)) $MVCish->processExceptionResponse($exception);
			exit(1);
		}
		return true;
	}

	// usually Environment and Exception work together to take care of this,
	// but for catastrophic failures, here's the dumb way
	private static function _buildErrorMessages($MVCish, $errno, $errstr, $errfile, $errline,array|\Throwable $trace=null):array {

		$isThrownWarning = false;
		$errstr = Exception::cleanWarningPrefix($errno,$errstr,$isThrownWarning);

		//hacky, but...
		if ($isThrownWarning) {
			$errConst = 'E_MVCISH_WARNING';
		}
		else {
			$errConst = self::translatePHPerrCode($errno);
		}

		$messages = [];
		$messages[] = $errConst.": $errstr"
			.(($errConst != 'E_MVCISH_WARNING') ? "; line $errline:$errfile" : '');

		if (self::isFatalPHPerrCode($errno)) {
			$messages[] = "TRACE: ".self::getTraceString(0,$trace);
		}
		return $messages;
	}


	public static function translatePHPerrCode($errno) {
		$e_type = '';
		switch ($errno) {
			case 1: $e_type = 'E_ERROR'; break;
			case 2: $e_type = 'E_WARNING'; break;
			case 4: $e_type = 'E_PARSE'; break;
			case 8: $e_type = 'E_NOTICE'; break;
			case 16: $e_type = 'E_CORE_ERROR'; break;
			case 32: $e_type = 'E_CORE_WARNING'; break;
			case 64: $e_type = 'E_COMPILE_ERROR'; break;
			case 128: $e_type = 'E_COMPILE_WARNING'; break;
			case 256: $e_type = 'E_USER_ERROR'; break;
			case 512: $e_type = 'E_USER_WARNING'; break;
			case 1024: $e_type = 'E_USER_NOTICE'; break;
			case 2048: $e_type = 'E_STRICT'; break;
			case 4096: $e_type = 'E_RECOVERABLE_ERROR'; break;
			case 8192: $e_type = 'E_DEPRECATED'; break;
			case 16384: $e_type = 'E_USER_DEPRECATED'; break;
			case 30719: $e_type = 'E_ALL'; break;
			default: $e_type = 'E_UNKNOWN'; break;
		}
		return $e_type;
	}
	public static function isFatalPHPerrCode($errno) {
		return in_array($errno,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR]);
	}


	public static function getTraceString(int $max=0,array|\Throwable $trace=null):string {
		$trace ??= debug_backtrace();
		return implode('; ',self::getTraceStrings($max,$trace));
	}

	public static function getTraceStrings(int $max=0,array|\Throwable $trace=null):array {
		if (is_object($trace)) {
			$trace = method_exists($trace,'getFilteredTrace') ?
				$trace->getFilteredTrace() : self::getFilteredTrace($max,$trace);
		}
		$trace ??= self::getFilteredTrace($max);

		$strings = [];
		foreach($trace as $t) {
			foreach(['file','class'] as $k) { $t[$k] ??= ''; }
			$strings[] = 
				(empty($t['file'])     ? '' :
					((basename($t['file']) ?? $t['file']).
						(empty($t['line']) ? '' : ' ('.$t['line'].')').': ')).
				(empty($t['class'])    ? '' : $t['class'].'->').
				(empty($t['function']) ? '' : 
					$t['function'].'('.	(empty($t['args']) ? '' :
						implode(',',
							array_map(function($v) {
								if (is_object($v) && method_exists($v,'__toString')) {
									$v = $v->__toString();
								}
								return 	is_string($v) ? ('"'.(strlen($v) > 7 ? substr($v,0,7).'...' : $v).'"') :
										(is_object($v) ? '$'.get_class($v) : strtoupper(gettype($v)));
							},$t['args'])
						)).')');
		}
		return $strings;
	}

	public static function getFilteredTrace(int $max=0,\Throwable $forException=null):array {
		// try to skip all the stuff what likely went into outputting the error.
		// it's best to try and capture the trace as soon as you can and pass it in,
		// either as an array, or if you're passing us an Exception then it's already stored.
		// If you don't pass in, we'll get it. But the sooner you capture it, the less junk
		// will have to be filtered, and more good info should remain.

		$ignoreUntil = null;
		if (isset($forException)) {
			$trace = method_exists($forException,'getOverrideTrace') ?
				$forException->getOverrideTrace() : $forException->getTrace();
			$ignoreUntil = ['file' => $forException->getFile(), 'line' => $forException->getLine()];
			//error_log("IE= ".$forException->getFile().' '.$forException->getLine());
		}
		else {
			$trace = debug_backtrace();
			array_shift($trace); // pop this call
		}

		foreach ($trace as $i => $t) {
			$skips = [];
			if (
				(isset($ignoreUntil) && !(isset($t['file']) && isset($t['line']) &&
					($t['file'] == $ignoreUntil['file']) && ($t['file'] == $ignoreUntil['file']))) ||

				(isset($t['class']) && (($t['class'] == 'AuntieWarhol\MVCish\Debug') ||
				(isset($t['file']) && str_contains($t['file'],'mvcish/src/Debug')) ||

				(isset($t['class']) && (($t['class'] == 'Exception') ||
					is_subclass_of($t['class'],'Exception'))) ||

				(isset($t['file']) && str_contains($t['file'],'mvcish/src/Exception')) ||

				(((isset($t['class']) && ($t['class'] == 'AuntieWarhol\MVCish\MVCish')) || 
				 (isset($t['file'])  && ($t['file'] == __FILE__))) &&
				in_array($t['function'],['logExceptionMessage','_error_handler','trigger_error',
					'throwWarning','getTraceString','getTraceStrings',
					'AuntieWarhol\MVCish\{closure}'])) ||
				
				((isset($t['class']) && (($t['class'] == 'AuntieWarhol\MVCish\Environment') ||
				  is_subclass_of($t['class'],'AuntieWarhol\MVCish\Environment'))) &&
				in_array($t['function'],['buildDefaultExceptionMessage','buildExceptionMessage']))
			) {
				$count = count($trace);
				$skips[] = $trace[$i]; unset($trace[$i]);
				//error_log('skipping '.$t['function'].' trace was '.$count.' now '.count($trace));
			}
			else {
				//error_log('keeping '.($t['file'].' ' ?? '').($t['class'] ?? '').'->'.$t['function'].' trace is '.count($trace));
				unset($ignoreUntil);
				break; //once we find a keeper, keep the rest
			}
		}
		// just in case we emptied it out
		if (empty($trace) && !empty($skips)) { $trace = $skips; }

		return ($max > 0) ? array_slice($trace,0,$max) : $trace;
	}
}
?>
