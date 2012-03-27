<?php

namespace FuseSource\Stomp\Helper;

class InputHelper
{
	public static function convertStringOptions(array $options)
	{
		foreach ($options as $key => $value) {
			if ($value === 'true') {
				$value = true;
			}

			if ($value === 'false') {
				$value = false;
			}

			$options[$key] = $value;
		}

		return $options;
	}
}