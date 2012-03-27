<?php

/**
 *
 * Copyright 2012 Max Beutel <me@maxbeutel.de>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace Stomp\Helper;

use PHPUnit_Framework_TestCase;

class InputHelperTest extends PHPUnit_Framework_TestCase
{
	public function testConvertStringOptionsTransformsStringToBool()
	{
		$options = InputHelper::convertStringOptions(['foo' => 'bar', 'one' => 'true', 'two' => 'false']);
		$this->assertSame(['foo' => 'bar', 'one' => true, 'two' => false], $options);
	}

	public function testConvertPhpOptionsTransformsBoolToString()
	{
		$options = InputHelper::convertPhpOptions(['foo' => 'bar', 'one' => true, 'two' => false]);
		$this->assertSame(['foo' => 'bar', 'one' => 'true', 'two' => 'false'], $options);
	}
}