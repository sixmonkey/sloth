<?php
if ( ! function_exists( 'debug' ) ) {
	/**
	 * Tracy\Debugger::barDump() shortcut.
	 *
	 * @tracySkipLocation
	 */
	function debug( $var ) {
		call_user_func_array( 'Tracy\Debugger::barDump', func_get_args() );

		return $var;
	}
}