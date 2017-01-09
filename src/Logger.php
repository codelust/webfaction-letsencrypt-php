<?php

namespace Frontiernxt;

class Logger 
{ 
	function __call($name, $arguments) 
		{ echo date('Y-m-d H:i:s')." [$name] ${arguments[0]}\n"; 
	}
}