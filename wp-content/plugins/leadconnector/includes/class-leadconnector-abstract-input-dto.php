<?php
/**
 *
 * LeadConnector inbound input: whitelist → validate → sanitize → input DTO. WordPress-native; fail-fast.
 *
 * @package LeadConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all inbound input DTOs.
 *
 * @package LeadConnector
 */
abstract class LeadConnector_Abstract_Input_DTO {

	/**
	 * Return the field validation schema for this DTO.
	 *
	 * @return array
	 */
	abstract public static function schema(): array;

	/**
	 * Build a DTO from validated and sanitized input.
	 *
	 * @param array $sanitized Sanitized input data.
	 * @return static
	 */
	abstract public static function from_validated( array $sanitized );
}
