<?php
if(!function_exists('debug')) {
	function debug($var) {
		bdump($var);
	}
}