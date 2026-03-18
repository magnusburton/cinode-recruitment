<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              cinode.com
 * @since             1.0.0
 * @package           Cinode_Recruitment
 *
 * @wordpress-plugin
 * Plugin Name:       Cinode
 * Plugin URI:        cinode.com
 * Description:       Integrate your WordPress site with Cinode's Recruitment module. Capture candidate and subcontractor applications directly into your Cinode instance.
 * Version:           1.6.0
 * Author:            Cinode
 * Author URI:        cinode.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cinode-recruitment
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 */
define('CINODE_RECRUITMENT_VERSION', '1.6.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cinode-recruitment-activator.php
 */
function activate_cinode_recruitment()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-cinode-recruitment-activator.php';
	Cinode_Recruitment_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cinode-recruitment-deactivator.php
 */
function deactivate_cinode_recruitment()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-cinode-recruitment-deactivator.php';
	Cinode_Recruitment_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_cinode_recruitment');
register_deactivation_hook(__FILE__, 'deactivate_cinode_recruitment');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-cinode-recruitment.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_cinode_recruitment()
{

	$plugin = new Cinode_Recruitment();
	$plugin->run();
}
run_cinode_recruitment();



function cinode_recruitment_route()
{
	register_rest_route(
		'cinode/v2',
		'cinode-recruitment',
		array(
			array(
				'methods'             => 'POST',
				'callback'            => 'cinodeRecruitmentPost',
				'permission_callback' => '__return_true',
				'args' =>  array(
					'firstName' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'First name'
					),
					'lastName' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Last name'
					),
					'email' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Email address'
					),
					'phone' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Phone ID'
					),
					'description' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Description'
					),
					'linkedInUrl' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'linkedIn  address'
					),
					'state' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'State',
						'value' => 0,
					),
					'currencyId' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'currencyId',
						'value' => 1,
					),
					'pipelineId' => array(
						'type' => 'int',
						'description' => 'pipelineId',
					),
					'pipelineStageId' => array(
						'type' => 'int',
						'description' => 'pipelineStageId',
					),
					'recruitmentManagerId' => array(
						'type' => 'int',
						'description' => 'recruitmentManagerId',
					),
					'teamId' => array(
						'type' => 'int',
						'description' => 'teamId',
					),
					'companyAddressId' => array(
						'type' => 'int',
						'description' => 'companyAddressId',
					),
					'recruitmentSourceId' => array(
						'type' => 'int',
						'description' => 'recruitmentSourceId',
					),
					'campaignCode' => array(
						'type' => 'string',
						'description' => 'campaignCode',
					),
					'availableFrom' => array(
						'type' => 'string',
						'description' => 'availableFrom',
					),

					'recipient_type' => array(
						'type' => 'string',
						'description' => 'candidate or subcontractor',
						'default' => 'candidate',
					),
					'gender' => array(
						'type' => 'integer',
						'description' => 'Gender (0=Other, 1=Male, 2=Female) — subcontractor mode only',
					),
					'subcontractorGroupIds' => array(
						'type' => 'array',
						'description' => 'Subcontractor group IDs to join',
					),
					'parse_cv' => array(
						'type' => 'string',
						'description' => 'Whether to import the uploaded CV into the subcontractor profile',
					),

					'files' => array(),
				)
			)
		)
	);
}


function cinodeRecruitmentPost($postData)
{
	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];

	$recipient_type = sanitize_text_field($postData['recipient_type'] ?? 'candidate');

	if ($recipient_type === 'subcontractor') {
		return cinode_recruitment_create_subcontractor($postData, $companyId, $token, $cinode_recruitment_options);
	}

	// --- Candidate flow (original) ---
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId . "/candidates";

	$body = array(
		'firstName'   => sanitize_text_field($postData['firstName']),
		'lastName'    => sanitize_text_field($postData['lastName']),
		'description' => sanitize_textarea_field($postData['description']),
		'email'       => sanitize_email($postData['email']),
		'phone'       => sanitize_text_field($postData['phone']),
		'linkedInUrl' => esc_url_raw($postData['linkedInUrl']),
		'state'       => intval($postData['state']),
		'currencyId'  => intval($postData['currencyId']),
	);

	// Only include optional integer fields when actually set; sending 0
	// causes the Cinode API to reject the request.
	$optional_int_fields = array(
		'pipelineId'           => 'pipelineId',
		'pipelineStageId'      => 'pipelineStageId',
		'recruitmentManagerId' => 'recruitmentManagerId',
		'teamId'               => 'teamId',
		'companyAddressId'     => 'companyAddressId',
	);
	foreach ($optional_int_fields as $post_key => $api_key) {
		$val = intval($postData[$post_key] ?? 0);
		if ($val > 0) {
			$body[$api_key] = $val;
		}
	}

	$recruitment_source_id = intval($postData['recruitmentSourceId'] ?? 0);
	if ($recruitment_source_id > 0) {
		$body['recruitmentSourceId'] = $recruitment_source_id;
	}

	$available_from = sanitize_text_field($postData['availableFrom'] ?? '');
	if ($available_from !== '') {
		$body['availableFromDate'] = $available_from;
	}

	$campaign_code = sanitize_text_field($postData['campaignCode'] ?? '');
	if ($campaign_code !== '') {
		$body['campaignCode'] = $campaign_code;
	}

	$args = array(
		'body' => wp_json_encode($body),
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$post_result   = wp_remote_post($url, $args);
	$response_code = wp_remote_retrieve_response_code($post_result);

	if ($response_code === 201) {
		$json_response = json_decode(wp_remote_retrieve_body($post_result), true);
		$candidateId   = $json_response['id'] ?? null;

		if ($candidateId && !empty($postData->get_file_params())) {
			cinode_recruitment_upload_file($postData, $candidateId);
		}

		cinode_recruitment_send_mail(sanitize_email($postData['email']));
	}

	return new WP_REST_Response(array('status' => $response_code), 200);
}

function cinode_recruitment_create_subcontractor($postData, $companyId, $token, $options)
{
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId . "/subcontractors";

	$language_id = intval($options['option_subcontractor_default_language_id'] ?? 0);
	if ($language_id < 1) {
		$language_id = 714; // Default to Swedish if not configured
	}

	// Generate a secure random password; the subcontractor sets their own via the welcome email.
	$password = wp_generate_password(20, true, true);

	$body = array(
		'firstName'       => sanitize_text_field($postData['firstName']),
		'lastName'        => sanitize_text_field($postData['lastName']),
		'email'           => sanitize_email($postData['email']),
		'phone'           => sanitize_text_field($postData['phone'] ?? ''),
		'gender'          => intval($postData['gender'] ?? 0),
		'languageId'      => $language_id,
		'profileLanguageId' => $language_id,
		'password'        => $password,
		'passwordConfirm' => $password,
		'currencyId'      => intval($postData['currencyId'] ?? 1),
		'createProfile'   => true,
	);

	if (!empty($postData['companyAddressId'])) {
		$body['companyAddressId'] = intval($postData['companyAddressId']);
	}

	$args = array(
		'body'    => wp_json_encode($body),
		'headers' => array(
			'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$post_result   = wp_remote_post($url, $args);
	$response_code = wp_remote_retrieve_response_code($post_result);

	if ($response_code === 201) {
		$json_response  = json_decode(wp_remote_retrieve_body($post_result), true);
		// id = subcontractor entity ID (used for URL paths like /attachments, /send-welcome-email)
		// companyUserId = user account ID (used for /users/{id}/profile/import and group membership)
		$subcontractorId = $json_response['id'];
		$companyUserId   = $json_response['companyUserId'];

		// Add to requested subcontractor groups
		$group_ids = $postData['subcontractorGroupIds'] ?? array();
		if (!is_array($group_ids)) {
			$group_ids = array_values(array_filter(array_map('intval', explode(',', (string) $group_ids))));
		}
		foreach ($group_ids as $group_id) {
			$group_id = intval($group_id);
			if ($group_id > 0) {
				cinode_recruitment_add_to_subcontractor_group($companyId, $token, $companyUserId, $group_id);
			}
		}

		// Parse CV first (reads from PHP tmp before it is moved by the attachment upload)
		$parse_cv = $postData['parse_cv'] ?? '0';
		if (($parse_cv === '1' || $parse_cv === true) && !empty($companyUserId) && !empty($postData->get_file_params())) {
			cinode_recruitment_import_subcontractor_profile($postData, $companyUserId, $companyId, $token);
		}

		// Upload file as attachment
		if (!empty($postData->get_file_params())) {
			cinode_recruitment_upload_subcontractor_file($postData, $subcontractorId, $companyId, $token);
		}

		// Send welcome email so the subcontractor can set their own password
		$welcome_url = "https://api.cinode.app/v0.1/companies/" . $companyId . "/subcontractors/" . $subcontractorId . "/send-welcome-email";
		wp_remote_post($welcome_url, array(
			'headers' => array(
				'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
		));

		// Send confirmation email to applicant
		cinode_recruitment_send_mail(sanitize_email($postData['email']));
	}

	return new WP_REST_Response(array('status' => $response_code), 200);
}

add_action('rest_api_init', 'cinode_recruitment_route');

function cinode_recruitment_upload_file($request, $candidateId)
{
	// Get the file
	$files = $request->get_file_params();

	if (empty($files['files']['tmp_name']) || !is_uploaded_file($files['files']['tmp_name'])) {
		return;
	}

	$allowed_mimes = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'rtf'  => 'application/rtf',
		'txt'  => 'text/plain',
	);
	$file_check = wp_check_filetype_and_ext($files['files']['tmp_name'], $files['files']['name'], $allowed_mimes);
	if (empty($file_check['ext']) || empty($file_check['type'])) {
		return;
	}

	$upload_dir  = wp_upload_dir();
	$target_dir  = $upload_dir['path'] . '/';
	$target_file = $target_dir . wp_unique_filename($target_dir, basename($files['files']['name']));

	move_uploaded_file($files['files']['tmp_name'], $target_file);

	$name = $files['files']['name'];
	$type = $file_check['type'];
	$path = $target_file;

	$file_data = file_get_contents($path);
	if ($file_data === false) {
		$file_data = '';
	}

	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];

	$url_attach = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/candidates/' . $candidateId . '/attachments';

	$boundary = cinode_recruitment_boundary();

	$body = '';
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename($path) . "\"\r\n";
	$body .= 'Content-Type: ' . $type . "\r\n\r\n";
	$body .= $file_data . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="title"' . "\r\n";
	$body .= 'Content-Type: application/json' . "\r\n\r\n";
	$body .= $name . "\r\n";
	$body .= '--' . $boundary . '--' . "\r\n";


	$args = array(
		'body' => $body,
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
			'Authorization' => 'Bearer ' . $token,
		),

	);

	$post_attach_result = wp_remote_post($url_attach, $args);

	wp_delete_file($path);

	return $post_attach_result;
}

function cinode_recruitment_upload_subcontractor_file($request, $subcontractorId, $companyId, $token)
{
	$files = $request->get_file_params();

	if (empty($files['files']['tmp_name']) || !is_uploaded_file($files['files']['tmp_name'])) {
		return;
	}

	$allowed_mimes = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'odt'  => 'application/vnd.oasis.opendocument.text',
		'rtf'  => 'application/rtf',
		'txt'  => 'text/plain',
	);
	$file_check = wp_check_filetype_and_ext($files['files']['tmp_name'], $files['files']['name'], $allowed_mimes);
	if (empty($file_check['ext']) || empty($file_check['type'])) {
		return;
	}

	$upload_dir  = wp_upload_dir();
	$target_dir  = $upload_dir['path'] . '/';
	$target_file = $target_dir . wp_unique_filename($target_dir, basename($files['files']['name']));

	move_uploaded_file($files['files']['tmp_name'], $target_file);

	$name = $files['files']['name'];
	$type = $file_check['type'];
	$path = $target_file;

	$file_data = file_get_contents($path);
	if ($file_data === false) {
		$file_data = '';
	}

	$url      = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/subcontractors/' . $subcontractorId . '/attachments';
	$boundary = cinode_recruitment_boundary();

	$body  = '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename($path) . "\"\r\n";
	$body .= 'Content-Type: ' . $type . "\r\n\r\n";
	$body .= $file_data . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="title"' . "\r\n";
	$body .= 'Content-Type: application/json' . "\r\n\r\n";
	$body .= $name . "\r\n";
	$body .= '--' . $boundary . '--' . "\r\n";

	$args = array(
		'body'    => $body,
		'headers' => array(
			'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$result = wp_remote_post($url, $args);
	wp_delete_file($path);

	return $result;
}

function cinode_recruitment_import_subcontractor_profile($request, $companyUserId, $companyId, $token)
{
	$files    = $request->get_file_params();
	$tmp_path = $files['files']['tmp_name'] ?? '';

	// The tmp file must still exist (called before the attachment upload moves it)
	if (empty($tmp_path) || !file_exists($tmp_path)) {
		return;
	}

	$file_data = file_get_contents($tmp_path);
	$file_name = $files['files']['name'];
	$file_type = $files['files']['type'];
	$boundary  = cinode_recruitment_boundary();

	$url = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/users/' . $companyUserId . '/profile/import';

	$body  = '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="File"; filename="' . $file_name . "\"\r\n";
	$body .= 'Content-Type: ' . $file_type . "\r\n\r\n";
	$body .= $file_data . "\r\n";
	$body .= '--' . $boundary . "\r\n";
	$body .= 'Content-Disposition: form-data; name="ImportSkills"' . "\r\n\r\n";
	$body .= "true\r\n";
	$body .= '--' . $boundary . '--' . "\r\n";

	$args = array(
		'body'    => $body,
		'headers' => array(
			'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 30,
	);

	return wp_remote_post($url, $args);
}

function cinode_recruitment_add_to_subcontractor_group($companyId, $token, $companyUserId, $groupId)
{
	$url  = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/subcontractors/groups/' . intval($groupId) . '/members';
	$body = wp_json_encode(array('companyUserSubcontractorId' => intval($companyUserId)));

	$args = array(
		'body'    => $body,
		'headers' => array(
			'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	return wp_remote_post($url, $args);
}

function cinode_recruitment_subcontractor_groups($label, $allowed_ids_string, $selection_mode)
{
	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token     = $cinode_recruitment_options['option_apiKey'];
	$url       = 'https://api.cinode.app/v0.1/companies/' . $companyId . '/subcontractors/groups';

	$args = array(
		'headers' => array(
			'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$get_result    = wp_remote_get($url, $args);
	$response_code = wp_remote_retrieve_response_code($get_result);

	if ($response_code !== 200) {
		return;
	}

	$groups = json_decode(wp_remote_retrieve_body($get_result), true);

	if (empty($groups) || !is_array($groups)) {
		return;
	}

	// Filter to allowed IDs if configured
	$allowed_ids = array();
	if (!empty($allowed_ids_string)) {
		$allowed_ids = array_values(array_filter(array_map('intval', explode(',', $allowed_ids_string))));
	}

	if (!empty($allowed_ids)) {
		$groups = array_values(array_filter($groups, function ($group) use ($allowed_ids) {
			return in_array(intval($group['id']), $allowed_ids, true);
		}));
		// Preserve the shortcode-defined order
		usort($groups, function ($a, $b) use ($allowed_ids) {
			return array_search(intval($a['id']), $allowed_ids) - array_search(intval($b['id']), $allowed_ids);
		});
	}

	if (empty($groups)) {
		return;
	}

	echo '<label>' . esc_html($label) . '</label><br>';

	if ($selection_mode === 'multiple') {
		foreach ($groups as $group) {
			$id   = intval($group['id']);
			$name = esc_html($group['name'] ?? '');
			echo '<label><input type="checkbox" name="subcontractorGroupIds[]" value="' . $id . '"> ' . $name . '</label><br>';
		}
	} else {
		echo '<select name="subcontractorGroupIds[]" id="subcontractorGroupId">';
		foreach ($groups as $group) {
			$id   = intval($group['id']);
			$name = esc_html($group['name'] ?? '');
			echo '<option value="' . $id . '">' . $name . '</option>';
		}
		echo '</select><br>';
	}
}

function cinode_recruitment_boundary()
{
	$alphabet    = 'abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789';
	$alphaLength = strlen($alphabet) - 1;
	$pass        = array();
	try {
		for ($i = 0; $i < 64; $i++) {
			$pass[] = $alphabet[random_int(0, $alphaLength)];
		}
	} catch (\Throwable $e) {
		// random_int() throws if the OS entropy source is unavailable.
		// Fall back to WordPress's own generator which has its own CSPRNG.
		return substr(wp_generate_password(64, false, false), 0, 64);
	}
	return implode($pass);
}

function cinode_recruitment_apiTokenCheck()
{
	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token     = $cinode_recruitment_options['option_apiKey'];

	$cache_key = 'cinode_token_valid_' . md5((string) $companyId . (string) $token);
	$cached    = get_transient($cache_key);
	if ($cached !== false) {
		return (bool) $cached;
	}

	$url  = 'https://api.cinode.app/v0.1/companies/' . $companyId;
	$args = array(
		'headers' => array(
			'Accept'        => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

	$get_result    = wp_remote_get($url, $args);
	$json_response = json_decode(wp_remote_retrieve_body($get_result), true);
	$is_valid      = !empty($json_response);

	set_transient($cache_key, $is_valid ? 1 : 0, 5 * MINUTE_IN_SECONDS);

	return $is_valid;
}

function cinode_recruitment_send_mail($email)
{
	$to = $email;
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$cinode_recruitment_options_sendmail = get_option('cinode_recruitment_options_sendmail');
	$subject = $cinode_recruitment_options_sendmail['option_subject'];
	$body = $cinode_recruitment_options_sendmail['option_message'];

	wp_mail($to, $subject, $body, $headers);
}

function cinode_recruitment_availableFrom($availableFrom_label)
{
?>
	<label for="availableFrom"><?php echo esc_html($availableFrom_label); ?></label><br>
	<input type="date" id="availableFrom" name="availableFrom" />
	<br>
	<?php
}

function cinode_recruitment_companyAddresses($location_label)
{
	$cinode_recruitment_options = get_option('cinode_recruitment_options');
	$companyId = $cinode_recruitment_options['option_companyId'];
	$token = $cinode_recruitment_options['option_apiKey'];
	$url = "https://api.cinode.app/v0.1/companies/" . $companyId;

	$args = array(
		'headers' => array(
			'Accept' => 'text/plain, application/json, text/json, application/xml, text/xml',
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
	);

		$get_result = wp_remote_get($url, $args);
	
		$response_code = wp_remote_retrieve_response_code($get_result);
	
		if ($response_code != 401) {
	
		$json_response = json_decode(wp_remote_retrieve_body($get_result), true);
	
		$addresses = isset($json_response['addresses']) && is_array($json_response['addresses']) ? $json_response['addresses'] : array();

		if (count($addresses) > 0) {

			?>
				<label for="companyAddressId"><?php echo esc_html($location_label); ?></label><br>
		
				<select name="companyAddressId" id="companyAddressId">
		
					<?php foreach ($addresses as $address) : ?>
						<option value="<?php echo esc_attr($address['id'] ?? ''); ?>"><?php echo esc_html($address['city'] ?? ''); ?></option>
					<?php endforeach; ?>
				</select>
				<br>
			<?php
			}
	
	} 
}

function cinode_recruitment_multiplepipelines($multiplepipelines_label, $pipelines_string, $stageIds)
{ 
	$pipelines_pairs = explode(',', $pipelines_string);

	$stage = explode(',', $stageIds);


	foreach ($pipelines_pairs as $pair) {
		$pipelines[] = explode(':', $pair);
	}


	echo '<label for="SelectedPipeline">' . esc_html($multiplepipelines_label) . '</label>';
	echo '<br><select id="selectedPipelineId" name="selectedPipelineId">';
	echo '<option value="">Select</option>';

	$i = 0;
	foreach ($pipelines as $pair) {
		echo '<option value="' . $pair[0] . '" stageId="' . $stage[$i] . '">' . $pair[1] . '</option>';
		$i++;
	}
	echo "</select><br>";
}

add_shortcode('cinode', 'cinode_recruitment_shortcode');

function cinode_recruitment_shortcode($atts = [])
{
	$atts = array_change_key_case((array) $atts, CASE_LOWER);

	$args = shortcode_atts(array(
		'pipelineid' => 0,
		'pipelinestageid' => 0,
		'recruitmentmanagerid' => 0,
		'teamid' => 0,
		'companyaddressid' => 0,
		'recruitmentsourceid' => 0,
		'campaigncode' => '',
		'currencyid' => 1,
		'multiplepipelines' => '',
		'multiplepipeline_stageid' => 0,
		'availableFrom' => 0,
		'availablefrom_label' => '',
		// add custom labels
		'firstname_label' => 'First name',
		'lastname_label' => 'Last name',
		'email_label' => 'E-mail',
		'phone_label' => 'Phone',
		'message_label' => 'Message',
		'linkedin_label' => 'LinkedIn Url',
		'location_label' => 'Choose location:',
		'multiplepipelines_label' => '',
		'attachment_label' => 'Attachment',
		'accept_label' => 'I accept that my personal data is processed in accordance with GDPR',
		'privacy_url' => '',
		'privacy_error' => 'You must accept the terms & conditions.',
		'submitbutton_label' => 'Submit your application',
		'successful-submit-msg' => 'Thanks for your application, we\'re looking forward to have a look at your profile!',
		'unsuccessful-submit-msg' => 'Your application is not sent!',
		'requiredfield_msg' => 'Required field.',
		'formtitle' => '',
		// Recipient type
		'recipient_type' => 'candidate',
		// Subcontractor groups
		'show_subcontractor_groups'     => '',
		'subcontractor_group_ids'       => '',
		'subcontractor_group_selection' => '',
		'groups_label'                  => 'Apply to group:',
		// CV settings
		'cv_required' => '',
		'parse_cv'    => '',
		// Auto-join groups (no selector shown)
		'auto_subcontractor_group_ids' => '',
		// Subcontractor-specific field labels
		'gender_label'       => 'Gender',
		'gender_option_other'  => 'Other / prefer not to say',
		'gender_option_male'   => 'Male',
		'gender_option_female' => 'Female',
	), $atts, $shortcode = "cinode");

	// Resolve settings: shortcode value overrides admin default.
	$cinode_options = get_option('cinode_recruitment_options');
	$cv_required = $args['cv_required'] !== '' ? (bool) $args['cv_required'] : (bool) ($cinode_options['option_cv_required'] ?? 0);
	$parse_cv    = $args['parse_cv'] !== ''    ? (bool) $args['parse_cv']    : (bool) ($cinode_options['option_parse_cv']     ?? 0);
	$show_subcontractor_groups   = $args['show_subcontractor_groups'] !== '' ? (bool) $args['show_subcontractor_groups'] : (bool) ($cinode_options['option_show_subcontractor_groups'] ?? 0);
	$subcontractor_group_ids     = $args['subcontractor_group_ids']   !== '' ? $args['subcontractor_group_ids']   : ($cinode_options['option_subcontractor_group_ids']       ?? '');
	$subcontractor_group_sel     = $args['subcontractor_group_selection'] !== '' ? $args['subcontractor_group_selection'] : ($cinode_options['option_subcontractor_group_selection'] ?? 'single');
	$auto_group_ids              = $args['auto_subcontractor_group_ids'] !== '' ? $args['auto_subcontractor_group_ids'] : ($cinode_options['option_auto_subcontractor_group_ids'] ?? '');
	$is_subcontractor = $args['recipient_type'] === 'subcontractor';

	// Unique counter so multiple shortcodes on the same page each get
	// distinct element IDs and their own per-form JS configuration.
	static $cinode_form_uid = 0;
	++$cinode_form_uid;
	$uid = $cinode_form_uid;

	// Pre-compute auto-group IDs (used in the data attribute below).
	$auto_ids_arr = array_values(array_filter(array_map('intval', explode(',', $auto_group_ids))));

	ob_start();
	?>

	<div class="wrap">

		<h2><?php echo esc_html($args['formtitle']); ?></h2>
		<div role="form" class="cinode-form"
			data-pipeline-id="<?php echo intval($args['pipelineid']); ?>"
			data-pipeline-stage-id="<?php echo intval($args['pipelinestageid']); ?>"
			data-recruitment-manager-id="<?php echo intval($args['recruitmentmanagerid']); ?>"
			data-team-id="<?php echo intval($args['teamid']); ?>"
			data-company-address-id="<?php echo intval($args['companyaddressid']); ?>"
			data-recruitment-source-id="<?php echo intval($args['recruitmentsourceid']); ?>"
			data-campaign-code="<?php echo esc_attr($args['campaigncode']); ?>"
			data-currency-id="<?php echo intval($args['currencyid']); ?>"
			data-recipient-type="<?php echo esc_attr($args['recipient_type']); ?>"
			data-cv-required="<?php echo $cv_required ? 'true' : 'false'; ?>"
			data-parse-cv="<?php echo $parse_cv ? 'true' : 'false'; ?>"
			data-auto-subcontractor-group-ids="<?php echo esc_attr(wp_json_encode($auto_ids_arr)); ?>"
			lang="en-US" dir="ltr">
			<form action="#" method="post" id="cinode-form-<?php echo esc_attr($uid); ?>" enctype="multipart/form-data">
				<input type="hidden" name="recipient_type" value="<?php echo esc_attr($args['recipient_type']); ?>">
				<input type="hidden" name="parse_cv" value="<?php echo $parse_cv ? '1' : '0'; ?>">
				<div>
					<label><?php echo esc_html($args['firstname_label']); ?> *<br>
						<span class=""><input type="text" id="first_name-input-<?php echo esc_attr($uid); ?>" name="first_name" value="" size="100%" class="text " aria-required="true" aria-invalid="false" placeholder=" "></span>
						<span role="alert" id="first_name-required-<?php echo esc_attr($uid); ?>" class="alert-required cinode-firstname-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span>
					</label><br>
					<label><?php echo esc_html($args['lastname_label']); ?> *<br>
						<span class=""><input type="text" id="last_name-input-<?php echo esc_attr($uid); ?>" name="last_name" value="" size="100%" class=" text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="last_name-required-<?php echo esc_attr($uid); ?>" class="alert-required cinode-lastname-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span></span> </label><br>
					<label><?php echo esc_html($args['email_label']); ?> *<br>
						<span class=""><input type="email" id="email-input-<?php echo esc_attr($uid); ?>" name="email" value="" size="100%" class=" text " aria-required="true" aria-invalid="false" placeholder=" ">
							<span role="alert" id="email-required-<?php echo esc_attr($uid); ?>" class="alert-required cinode-email-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span></span>
					</label><br>
					<label><?php echo esc_html($args['phone_label']); ?><br>
						<span class=""><input type="text" id="phone-input-<?php echo esc_attr($uid); ?>" placeholder=" " name="phone" value="" size="100%" class=" text" aria-required="false" aria-invalid="false"></span>
						<span role="alert" id="phone-required-<?php echo esc_attr($uid); ?>" class="alert-required cinode-phone-required" style="display: none"><?php echo esc_html($args['requiredfield_msg']); ?></span>
						</span>
					</label><br>
					<label><?php echo esc_html($args['message_label']); ?> <br>
						<textarea class="autosize" cols="20" id="description-input-<?php echo esc_attr($uid); ?>" name="Description" size="100%" rows="2" style="overflow-wrap: break-word; resize: vertical; height: 150px;"></textarea>
					</label><br>
					<label><?php echo esc_html($args['linkedin_label']); ?><br>
						<input data-val="true" size="100%" id="LinkedInUrl-<?php echo esc_attr($uid); ?>" name="LinkedInUrl" type="text" value="">
					</label> <br>

					<?php if ($is_subcontractor) : ?>
					<label><?php echo esc_html($args['gender_label']); ?> *<br>
						<select id="gender-input-<?php echo esc_attr($uid); ?>" name="gender">
							<option value="0"><?php echo esc_html($args['gender_option_other']); ?></option>
							<option value="1"><?php echo esc_html($args['gender_option_male']); ?></option>
							<option value="2"><?php echo esc_html($args['gender_option_female']); ?></option>
						</select>
						<span role="alert" id="gender-required-<?php echo esc_attr($uid); ?>" class="alert-required cinode-gender-required" style="display:none"><?php echo esc_html($args['requiredfield_msg']); ?></span>
					</label><br>
					<?php endif; ?>

					<?php
					$location_label = $args['location_label'];
					if ($location_label != '') {
						cinode_recruitment_companyAddresses($location_label);
					}

					$availableFrom_label = $args['availablefrom_label'];
					if ($availableFrom_label != '') {
						cinode_recruitment_availableFrom($availableFrom_label);
					}

					// Subcontractors cannot be placed into pipelines; only candidates can.
					if (!$is_subcontractor) {
						$multiplepipelines_label = $args['multiplepipelines_label'];
						$pipelines_string = $args['multiplepipelines'];
						$pipelines_stageId = $args['multiplepipeline_stageid'];

						if ($pipelines_string) {
							cinode_recruitment_multiplepipelines($multiplepipelines_label, $pipelines_string, $pipelines_stageId);
						}
					}

					if ($is_subcontractor && $show_subcontractor_groups) {
						cinode_recruitment_subcontractor_groups($args['groups_label'], $subcontractor_group_ids, $subcontractor_group_sel);
					}

					?>
					<br>
					<div for="file-upload-<?php echo esc_attr($uid); ?>" class="custom-file-upload ">
						<?php echo esc_html($args['attachment_label']); ?><?php if ($cv_required) echo ' *'; ?>
						<input id="file-upload-<?php echo esc_attr($uid); ?>" class="file-upload" type="file" />
						<label class="file-name"></label>
						<?php if ($cv_required) : ?>
						<span role="alert" id="file-required-<?php echo esc_attr($uid); ?>" class="alert-required cinode-file-required" style="display:none"><?php echo esc_html($args['requiredfield_msg']); ?></span>
						<?php endif; ?>
					</div>
					<br>
					<input type="checkbox" name="terms" id="terms-<?php echo esc_attr($uid); ?>">
					<a href="<?php echo esc_url($args['privacy_url']); ?>" rel="noopener noreferrer" target="_blank">
						<?php echo esc_html($args['accept_label']); ?></a>
					<br>
					<span id="terms-validate-<?php echo esc_attr($uid); ?>" class="cinode-terms-validate" style="display:none; color:red;"> <?php echo esc_html($args['privacy_error']); ?></span>
					<input type="hidden" name="g-recaptcha-response" value="" id="g-recaptcha-response-<?php echo esc_attr($uid); ?>">
					<?php do_action('c4wp_captcha_form_field'); ?>
				</div>
				<div class="row">
					<div>
						<br>
						<p><input type="submit" id="submit-<?php echo esc_attr($uid); ?>" value="<?php echo esc_attr($args['submitbutton_label']); ?>"></p>
					</div>
				</div>
				<div class="spinner" style="display: none;">
					<div class="bounce1"></div>
					<div class="bounce2"></div>
					<div class="bounce3"></div>
				</div>

				<div class="alert cinode-success-msg" id="successful-submit-msg-<?php echo esc_attr($uid); ?>" style="display:none; background: green; color: white; text-align: center;">
					<?php echo esc_html($args['successful-submit-msg']); ?>
				</div>
				<div class="alert cinode-error-msg" id="unsuccessful-submit-msg-<?php echo esc_attr($uid); ?>" style="display: none; background: red; color: white; text-align: center;">
					<?php echo esc_html($args['unsuccessful-submit-msg']); ?>
				</div>
			</form>
		</div>
	</div>


<?php
	return ob_get_clean();
}
