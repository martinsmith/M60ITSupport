<?php
/**
 *
 * LeadConnector Plugin
 * Copyright (C) 2020-2026 LeadConnector
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * @package LeadConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps content with a validity flag for custom-value placeholder replacement.
 *
 * @package LeadConnector
 */
class LeadConnector_Custom_Value_String_Replacable {

	/**
	 * Raw content to process.
	 *
	 * @var mixed
	 */
	private $content;

	/**
	 * Whether the content is eligible for placeholder replacement.
	 *
	 * @var bool
	 */
	private $is_valid;

	/**
	 * LeadConnector_Custom_Value_String_Replacable constructor.
	 *
	 * @param mixed $content  Content value.
	 * @param bool  $is_valid Whether the content is valid.
	 */
	public function __construct( $content, $is_valid ) {
		$this->content  = $content;
		$this->is_valid = (bool) $is_valid;
	}

	/**
	 * Get the content value.
	 */
	public function get_content() {
		return $this->content;
	}

	/**
	 * Check if the return value is valid.
	 */
	public function is_valid() {
		return $this->is_valid ?? false;
	}
}
