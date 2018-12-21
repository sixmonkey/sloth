<?php
use Tracy\Debugger;
Debugger::$editorMapping = [
	'/var/www' => '[[ my path ]]'
];
Debugger::$editor = 'phpstorm://open/?file=%file&line=%line';