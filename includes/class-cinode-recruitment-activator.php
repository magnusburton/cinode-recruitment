<?php

/**
 * Fired during plugin activation
 *
 * @link       cinode.com
 * @since      1.0.0
 *
 * @package    Cinode_Recruitment
 * @subpackage Cinode_Recruitment/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Cinode_Recruitment
 * @subpackage Cinode_Recruitment/includes
 * @author     Cinode <info@cinode.com>
 */
class Cinode_Recruitment_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		cinode_recruitment_register_settings();

	}

}
