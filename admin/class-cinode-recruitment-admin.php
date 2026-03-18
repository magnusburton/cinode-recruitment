<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       cinode.com
 * @since      1.0.0
 *
 * @package    Cinode_Recruitment
 * @subpackage Cinode_Recruitment/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cinode_Recruitment
 * @subpackage Cinode_Recruitment/admin
 * @author     Cinode <info@cinode.com>
 */
class Cinode_Recruitment_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cinode-recruitment-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cinode-recruitment-admin.js', array( 'jquery' ), $this->version, false );

	}

}
function cinode_recruitment_create_menu()
{

	$iconUrl = plugin_dir_url(__FILE__) . '../images/icon-24x24.png';
	// Create new top-level menu
	add_menu_page('Cinode Recruitment Plugin Page', 'Cinode Recruitment Plugin', 'manage_options', 'cinode_recruitment_main_menu', 'cinode_recruitment_settings_page', $iconUrl);

	// Call register settings function
	add_action('admin_init', 'cinode_recruitment_register_settings');
}
add_action('admin_menu', 'cinode_recruitment_create_menu');

function cinode_recruitment_register_settings()
{

	// Register our settings
	register_setting('cinode_recruitment-settings-group', 'cinode_recruitment_options', 'cinode_recruitment_sanitize_options');
	register_setting('cinode_recruitment-settings-mail', 'cinode_recruitment_options_sendmail', 'cinode_recruitment_sanitize_options_sendmail');	
}

function cinode_recruitment_sanitize_options($input)
{
	$existing = get_option('cinode_recruitment_options', array());

	$input['option_companyId'] = sanitize_text_field($input['option_companyId'] ?? '');

	// Preserve the existing API key when the masked placeholder '***' is submitted.
	if (isset($input['option_apiKey']) && $input['option_apiKey'] === '***') {
		$input['option_apiKey'] = $existing['option_apiKey'] ?? '';
	} else {
		$input['option_apiKey'] = sanitize_text_field($input['option_apiKey'] ?? '');
	}

	$input['option_show_subcontractor_groups']         = isset($input['option_show_subcontractor_groups']) ? 1 : 0;
	$raw_group_ids = $input['option_subcontractor_group_ids'] ?? '';
	if (is_array($raw_group_ids)) {
		$input['option_subcontractor_group_ids'] = implode(',', array_filter(array_map('intval', $raw_group_ids)));
	} else {
		$input['option_subcontractor_group_ids'] = sanitize_text_field($raw_group_ids);
	}
	$allowed_selections                                = array('single', 'multiple');
	$raw_selection                                     = $input['option_subcontractor_group_selection'] ?? 'single';
	$input['option_subcontractor_group_selection']     = in_array($raw_selection, $allowed_selections, true) ? $raw_selection : 'single';
	$input['option_subcontractor_default_language_id'] = intval($input['option_subcontractor_default_language_id'] ?? 1);
	$input['option_cv_required']                       = isset($input['option_cv_required']) ? 1 : 0;
	$input['option_parse_cv']                          = isset($input['option_parse_cv']) ? 1 : 0;
	$raw_auto_ids = $input['option_auto_subcontractor_group_ids'] ?? '';
	if (is_array($raw_auto_ids)) {
		$input['option_auto_subcontractor_group_ids'] = implode(',', array_filter(array_map('intval', $raw_auto_ids)));
	} else {
		$input['option_auto_subcontractor_group_ids'] = sanitize_text_field($raw_auto_ids);
	}

	return $input;
}
function cinode_recruitment_sanitize_options_sendmail($input)
{
	$input['option_subject'] = sanitize_text_field($input['option_subject'] ?? '');
	$input['option_message'] = sanitize_textarea_field($input['option_message'] ?? '');

	return $input;
}

function cinode_recruitment_admin_fetch_groups()
{
	$opts      = get_option('cinode_recruitment_options', array());
	$companyId = $opts['option_companyId'] ?? '';
	$token     = $opts['option_apiKey'] ?? '';

	if (empty($companyId) || empty($token)) {
		return array();
	}

	$cache_key = 'cinode_admin_groups_' . md5((string) $companyId . $token);
	$cached    = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	$url  = 'https://api.cinode.app/v0.1/companies/' . intval($companyId) . '/subcontractors/groups';
	$args = array(
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 10,
	);

	$response = wp_remote_get($url, $args);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		return array();
	}

	$groups = json_decode(wp_remote_retrieve_body($response), true);
	$groups = is_array($groups) ? $groups : array();

	set_transient($cache_key, $groups, 5 * MINUTE_IN_SECONDS);

	return $groups;
}

function cinode_recruitment_admin_fetch_languages()
{
	$opts  = get_option('cinode_recruitment_options', array());
	$token = $opts['option_apiKey'] ?? '';

	if (empty($token)) {
		return array();
	}

	$cache_key = 'cinode_admin_languages_' . md5($token);
	$cached    = get_transient($cache_key);
	if ($cached !== false) {
		return $cached;
	}

	$url  = 'https://api.cinode.app/v0.1/languages';
	$args = array(
		'headers' => array(
			'Accept'        => 'application/json',
			'Authorization' => 'Bearer ' . $token,
		),
		'timeout' => 10,
	);

	$response = wp_remote_get($url, $args);
	if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
		return array();
	}

	$languages = json_decode(wp_remote_retrieve_body($response), true);
	$languages = is_array($languages) ? $languages : array();

	set_transient($cache_key, $languages, HOUR_IN_SECONDS);

	return $languages;
}

function cinode_recruitment_settings_page()
{
	$cinode_opts = get_option('cinode_recruitment_options', array());
	$api_valid   = cinode_recruitment_apiTokenCheck();
	$apiFieldVal = $api_valid ? '***' : '';

	$all_groups         = $api_valid ? cinode_recruitment_admin_fetch_groups() : array();
	$all_languages      = $api_valid ? cinode_recruitment_admin_fetch_languages() : array();
	$selected_group_ids = array_filter(array_map('intval', explode(',', $cinode_opts['option_subcontractor_group_ids'] ?? '')));
	$selected_auto_ids  = array_filter(array_map('intval', explode(',', $cinode_opts['option_auto_subcontractor_group_ids'] ?? '')));
	$selected_lang_id   = intval($cinode_opts['option_subcontractor_default_language_id'] ?? 1);

	$mail_opts = get_option('cinode_recruitment_options_sendmail', array());
	if (empty($mail_opts)) {
		$mail_opts = array(
			'option_subject' => 'Thanks for your application',
			'option_message' => 'Thank you for your application. We will look into your application and get back to you soon.',
		);
	}
?>
<div class="wrap cinode-wrap">

	<div class="cinode-page-header">
		<h1 class="cinode-page-title">
			<span class="dashicons dashicons-groups" aria-hidden="true"></span>
			Cinode Recruitment
		</h1>
		<?php if ($api_valid) : ?>
			<span class="cinode-badge cinode-badge--active">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Connected
			</span>
		<?php else : ?>
			<span class="cinode-badge cinode-badge--inactive">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span> Not connected — check your API credentials
			</span>
		<?php endif; ?>
	</div>

	<nav class="nav-tab-wrapper cinode-nav-tabs" aria-label="Plugin settings sections">
		<a href="#tab-api" class="nav-tab nav-tab-active" data-tab="tab-api">
			<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
			<span class="cinode-tab-label">API &amp; Activation</span>
		</a>
		<a href="#tab-subcontractor" class="nav-tab" data-tab="tab-subcontractor">
			<span class="dashicons dashicons-businessperson" aria-hidden="true"></span>
			<span class="cinode-tab-label">Subcontractor &amp; CV</span>
		</a>
		<a href="#tab-email" class="nav-tab" data-tab="tab-email">
			<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
			<span class="cinode-tab-label">Email Confirmation</span>
		</a>
		<a href="#tab-shortcode" class="nav-tab" data-tab="tab-shortcode">
			<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
			<span class="cinode-tab-label">Shortcode Reference</span>
		</a>
		<a href="#tab-spam" class="nav-tab" data-tab="tab-spam">
			<span class="dashicons dashicons-shield" aria-hidden="true"></span>
			<span class="cinode-tab-label">Spam Protection</span>
		</a>
	</nav>

	<!-- ============================================================
	     MAIN SETTINGS FORM — API credentials + Subcontractor & CV
	     Both tab sections share this form so saving from either tab
	     preserves all settings in cinode_recruitment_options.
	     ============================================================ -->
	<form method="post" action="options.php" id="cinode-main-settings-form">
		<?php settings_fields('cinode_recruitment-settings-group'); ?>

		<!-- TAB: API & Activation -->
		<div id="tab-api" class="cinode-tab-content">
			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
						API Credentials
					</h2>
				</div>
				<div class="inside">
					<p>Enter your Cinode <strong>Company ID</strong> and <strong>API Token</strong> to activate the plugin. You can find these in your Cinode account settings.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_company_id">Company ID</label>
							</th>
							<td>
								<input
									type="text"
									id="cinode_company_id"
									name="cinode_recruitment_options[option_companyId]"
									value="<?php echo esc_attr($cinode_opts['option_companyId'] ?? ''); ?>"
									class="regular-text"
									placeholder="e.g. 1234"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_api_key">API Token</label>
							</th>
							<td>
								<input
									type="password"
									id="cinode_api_key"
									name="cinode_recruitment_options[option_apiKey]"
									value="<?php echo esc_attr($apiFieldVal); ?>"
									class="regular-text"
									autocomplete="new-password"
									placeholder="<?php echo $api_valid ? 'Token saved \u2014 enter a new value to replace it' : 'Paste your API token here'; ?>"
								/>
								<p class="description"><?php echo $api_valid ? 'Your token is saved. Leave the field showing <code>***</code> to keep it unchanged.' : 'Paste the token from your Cinode account.'; ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button('Save API Settings', 'primary large', 'submit', true); ?>
				</div>
			</div>
		</div><!-- #tab-api -->

		<!-- TAB: Subcontractor & CV -->
		<div id="tab-subcontractor" class="cinode-tab-content" style="display:none;">

			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-groups" aria-hidden="true"></span>
						Subcontractor Groups
					</h2>
				</div>
				<div class="inside">
					<p>Control how the public form presents subcontractor group selection to applicants.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">Show group selector</th>
							<td>
								<fieldset>
									<label for="cinode_show_groups">
										<input
											type="checkbox"
											id="cinode_show_groups"
											name="cinode_recruitment_options[option_show_subcontractor_groups]"
											value="1"
											<?php checked(1, $cinode_opts['option_show_subcontractor_groups'] ?? 0); ?>
										/>
										Enable the group selector when the form is used in subcontractor mode
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_group_ids">Groups to display</label>
							</th>
							<td>
								<?php if (!empty($all_groups)) : ?>
									<select
										id="cinode_group_ids"
										name="cinode_recruitment_options[option_subcontractor_group_ids][]"
										multiple="multiple"
										size="<?php echo min(count($all_groups), 8); ?>"
										style="min-width:14rem;"
									>
										<?php foreach ($all_groups as $grp) :
											$grp_id   = intval($grp['id']);
											$grp_name = esc_html($grp['name'] ?? '#' . $grp_id);
										?>
											<option value="<?php echo $grp_id; ?>" <?php selected(in_array($grp_id, $selected_group_ids, true)); ?>><?php echo $grp_name; ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description">Hold <kbd>Ctrl</kbd> / <kbd>Cmd</kbd> to select multiple groups. Leave all unselected to show every group.</p>
								<?php else : ?>
									<input
										type="text"
										id="cinode_group_ids"
										name="cinode_recruitment_options[option_subcontractor_group_ids]"
										value="<?php echo esc_attr($cinode_opts['option_subcontractor_group_ids'] ?? ''); ?>"
										class="regular-text"
										placeholder="e.g. 12,34,56"
									/>
									<p class="description">Comma-separated group IDs. Leave empty to show all groups.</p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_group_selection">Selection mode</label>
							</th>
							<td>
								<select id="cinode_group_selection" name="cinode_recruitment_options[option_subcontractor_group_selection]">
									<option value="single" <?php selected('single', $cinode_opts['option_subcontractor_group_selection'] ?? 'single'); ?>>Single &mdash; dropdown</option>
									<option value="multiple" <?php selected('multiple', $cinode_opts['option_subcontractor_group_selection'] ?? 'single'); ?>>Multiple &mdash; checkboxes</option>
								</select>
								<p class="description">How the applicant selects their group(s) in the form.</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Auto-join groups</th>
							<td>
								<?php if (!empty($all_groups)) : ?>
									<fieldset>
										<?php foreach ($all_groups as $grp) :
											$grp_id   = intval($grp['id']);
											$grp_name = esc_html($grp['name'] ?? '#' . $grp_id);
										?>
											<label>
												<input
													type="checkbox"
													name="cinode_recruitment_options[option_auto_subcontractor_group_ids][]"
													value="<?php echo $grp_id; ?>"
													<?php checked(in_array($grp_id, $selected_auto_ids, true)); ?>
												/>
												<?php echo $grp_name; ?>
											</label><br>
										<?php endforeach; ?>
									</fieldset>
									<p class="description">Every new subcontractor is silently added to the ticked groups — no selector shown to the applicant. Can be overridden per shortcode: <code>auto_subcontractor_group_ids="12,34"</code>.</p>
								<?php else : ?>
									<input
										type="text"
										id="cinode_auto_groups"
										name="cinode_recruitment_options[option_auto_subcontractor_group_ids]"
										value="<?php echo esc_attr($cinode_opts['option_auto_subcontractor_group_ids'] ?? ''); ?>"
										class="regular-text"
										placeholder="e.g. 12,34"
									/>
									<p class="description">Comma-separated group IDs every new subcontractor is automatically added to — no selector is shown to the applicant. Can be overridden per shortcode: <code>auto_subcontractor_group_ids="12,34"</code>.</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
						CV Upload
					</h2>
				</div>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">CV required by default</th>
							<td>
								<fieldset>
									<label for="cinode_cv_required">
										<input
											type="checkbox"
											id="cinode_cv_required"
											name="cinode_recruitment_options[option_cv_required]"
											value="1"
											<?php checked(1, $cinode_opts['option_cv_required'] ?? 0); ?>
										/>
										Make the file upload field mandatory
									</label>
									<p class="description">Override per shortcode: <code>cv_required="1"</code> or <code>cv_required="0"</code>.</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">Parse CV on upload</th>
							<td>
								<fieldset>
									<label for="cinode_parse_cv">
										<input
											type="checkbox"
											id="cinode_parse_cv"
											name="cinode_recruitment_options[option_parse_cv]"
											value="1"
											<?php checked(1, $cinode_opts['option_parse_cv'] ?? 0); ?>
										/>
										Import the uploaded CV into the subcontractor's Cinode profile (asynchronous)
									</label>
									<p class="description">Applies to subcontractors only. Override per shortcode: <code>parse_cv="1"</code> or <code>parse_cv="0"</code>.</p>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-translation" aria-hidden="true"></span>
						Localisation
					</h2>
				</div>
				<div class="inside">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_lang_id">Default language</label>
							</th>
							<td>
								<?php if (!empty($all_languages)) : ?>
									<select id="cinode_lang_id" name="cinode_recruitment_options[option_subcontractor_default_language_id]">
										<?php foreach ($all_languages as $lang) :
											$lang_id   = intval($lang['languageId'] ?? $lang['id'] ?? 0);
											$lang_name = esc_html($lang['name'] ?? 'Language ' . $lang_id);
										?>
											<option value="<?php echo $lang_id; ?>" <?php selected($selected_lang_id, $lang_id); ?>><?php echo $lang_name; ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input
										type="number"
										id="cinode_lang_id"
										min="1"
										name="cinode_recruitment_options[option_subcontractor_default_language_id]"
										value="<?php echo esc_attr($selected_lang_id); ?>"
										class="small-text"
									/>
								<?php endif; ?>
								<p class="description">Language used when creating a subcontractor account in Cinode. Required by the API.</p>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<?php submit_button('Save Subcontractor Settings', 'primary large', 'submit', true); ?>
		</div><!-- #tab-subcontractor -->

	</form><!-- #cinode-main-settings-form -->

	<!-- ============================================================
	     EMAIL SETTINGS FORM
	     ============================================================ -->
	<form method="post" action="options.php" id="cinode-email-settings-form">
		<?php settings_fields('cinode_recruitment-settings-mail'); ?>

		<!-- TAB: Email Confirmation -->
		<div id="tab-email" class="cinode-tab-content" style="display:none;">
			<div class="postbox cinode-card">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
						Confirmation Email
					</h2>
				</div>
				<div class="inside">
					<p>This email is sent automatically to the applicant after a successful form submission.</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="cinode_mail_subject">Subject</label>
							</th>
							<td>
								<input
									type="text"
									id="cinode_mail_subject"
									name="cinode_recruitment_options_sendmail[option_subject]"
									value="<?php echo esc_attr($mail_opts['option_subject']); ?>"
									class="large-text"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cinode_mail_message">Message body</label>
							</th>
							<td>
								<textarea
									id="cinode_mail_message"
									name="cinode_recruitment_options_sendmail[option_message]"
									class="large-text"
									rows="6"
								><?php echo esc_textarea($mail_opts['option_message']); ?></textarea>
								<p class="description">Plain-text message sent to the candidate.</p>
							</td>
						</tr>
					</table>
					<p class="cinode-notice cinode-notice--info">
						<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						To use a custom SMTP server, install the <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" rel="noopener noreferrer"><strong>WP Mail SMTP</strong></a> plugin.
					</p>
					<?php submit_button('Save Email Settings', 'primary large', 'submit', true); ?>
				</div>
			</div>
		</div><!-- #tab-email -->

	</form><!-- #cinode-email-settings-form -->

	<!-- TAB: Shortcode Reference (no form) -->
	<div id="tab-shortcode" class="cinode-tab-content" style="display:none;">

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span> Quick Start</h2>
			</div>
			<div class="inside">
				<p>Paste the shortcode into any WordPress page or post. The simplest version:</p>
				<div class="cinode-code-block">
					<code id="sc-basic">[cinode]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-basic" aria-label="Copy shortcode">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copy
					</button>
				</div>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-filter" aria-hidden="true"></span> Core Parameters</h2>
			</div>
			<div class="inside">
				<p>All parameters are optional. If you set <code>pipelineId</code>, you must also set <code>pipelineStageId</code>.</p>
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Default</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>recipient_type</code></td><td><code>"candidate"</code></td><td>Who the form creates: <code>"candidate"</code> or <code>"subcontractor"</code>.</td></tr>
						<tr><td><code>formtitle</code></td><td><code>""</code></td><td>Heading displayed above the form. Leave empty to show no heading.</td></tr>
						<tr><td><code>pipelineId</code></td><td><code>0</code></td><td>Cinode pipeline ID to assign the candidate to.</td></tr>
						<tr><td><code>pipelineStageId</code></td><td><code>0</code></td><td>Stage within the pipeline. <strong>Required</strong> when <code>pipelineId</code> is set.</td></tr>
						<tr><td><code>recruitmentManagerId</code></td><td><code>0</code></td><td>Cinode user ID of the responsible recruitment manager.</td></tr>
						<tr><td><code>teamId</code></td><td><code>0</code></td><td>Team ID to associate with the application.</td></tr>
						<tr><td><code>companyAddressId</code></td><td><code>0</code></td><td>Pre-select a company address. When <code>0</code> the location dropdown fetches all addresses.</td></tr>
						<tr><td><code>recruitmentSourceId</code></td><td><code>0</code></td><td>Source (channel) ID for tracking where the application came from.</td></tr>
						<tr><td><code>campaignCode</code></td><td><code>""</code></td><td>Campaign tracking code.</td></tr>
						<tr><td><code>currencyId</code></td><td><code>1</code></td><td>Currency ID used for any salary-related fields.</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-tag" aria-hidden="true"></span> Field Label Parameters</h2>
			</div>
			<div class="inside">
				<p>Override any field label. Set a parameter to an empty string (<code>""</code>) to hide that field entirely.</p>
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Hideable?</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>firstname_label</code></td><td>&mdash;</td><td>Label for the first name field.</td></tr>
						<tr><td><code>lastname_label</code></td><td>&mdash;</td><td>Label for the last name field.</td></tr>
						<tr><td><code>email_label</code></td><td>&mdash;</td><td>Label for the email address field.</td></tr>
						<tr><td><code>phone_label</code></td><td>&mdash;</td><td>Label for the phone number field.</td></tr>
						<tr><td><code>message_label</code></td><td>&mdash;</td><td>Label for the message / cover letter field.</td></tr>
						<tr><td><code>linkedin_label</code></td><td>&mdash;</td><td>Label for the LinkedIn profile URL field.</td></tr>
						<tr><td><code>location_label</code></td><td>&#10003; set to <code>""</code></td><td>Label for the location field. Leave empty to hide the field.</td></tr>
						<tr><td><code>attachment_label</code></td><td>&mdash;</td><td>Label for the file upload / CV field.</td></tr>
						<tr><td><code>accept_label</code></td><td>&mdash;</td><td>Label for the GDPR / privacy acceptance checkbox.</td></tr>
						<tr><td><code>availableFrom_label</code></td><td>&#10003; omit to hide</td><td>Label for the &ldquo;available from&rdquo; date field. The field is hidden unless this attribute is present.</td></tr>
						<tr><td><code>submitbutton_label</code></td><td>&mdash;</td><td>Text shown on the submit button.</td></tr>
						<tr><td><code>groups_label</code></td><td>&mdash;</td><td>Label for the subcontractor group selector (subcontractor mode only).</td></tr>
						<tr><td><code>gender_label</code></td><td>&mdash;</td><td>Label for the gender field (subcontractor mode only).</td></tr>
						<tr><td><code>gender_option_male</code></td><td>&mdash;</td><td>Label for the &ldquo;Male&rdquo; gender option.</td></tr>
						<tr><td><code>gender_option_female</code></td><td>&mdash;</td><td>Label for the &ldquo;Female&rdquo; gender option.</td></tr>
						<tr><td><code>gender_option_other</code></td><td>&mdash;</td><td>Label for the &ldquo;Other / prefer not to say&rdquo; gender option.</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Message &amp; Validation Parameters</h2>
			</div>
			<div class="inside">
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr><th>Parameter</th><th>Description</th></tr>
					</thead>
					<tbody>
						<tr><td><code>privacy_url</code></td><td>URL linked from the GDPR/privacy checkbox label (e.g. <code>"https://example.com/privacy"</code>).</td></tr>
						<tr><td><code>privacy_error</code></td><td>Error message shown when the privacy checkbox is not ticked.</td></tr>
						<tr><td><code>successful-submit-msg</code></td><td>Message shown to the applicant after a successful submission.</td></tr>
						<tr><td><code>unsuccessful-submit-msg</code></td><td>Message shown when the submission fails.</td></tr>
						<tr><td><code>requiredfield_msg</code></td><td>Generic message used for unfilled required fields.</td></tr>
						<tr><td><code>cv_required</code></td><td>Override the global CV-required setting: <code>"1"</code> = mandatory, <code>"0"</code> = optional.</td></tr>
						<tr><td><code>parse_cv</code></td><td>Override the global parse-CV setting (subcontractors only): <code>"1"</code> or <code>"0"</code>.</td></tr>
						<tr><td><code>auto_subcontractor_group_ids</code></td><td>Override the global auto-join group IDs for this shortcode instance.</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-list-view" aria-hidden="true"></span> Multiple Pipelines</h2>
			</div>
			<div class="inside">
				<p>Let applicants choose from several open positions via a dropdown. Map each pipeline to its corresponding stage ID in order.</p>
				<table class="widefat striped cinode-ref-table">
					<thead>
						<tr>
							<th>Parameter</th>
							<th>Example value</th>
							<th>Description</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>multiplepipelines</code></td>
							<td><code>"1235:Role A,1676:Role B"</code></td>
							<td>Comma-separated <code>pipelineId:Label</code> pairs shown in the dropdown.</td>
						</tr>
						<tr>
							<td><code>multiplepipeline_stageid</code></td>
							<td><code>"6003,7783"</code></td>
							<td>Comma-separated stage IDs, one per pipeline, in the same order.</td>
						</tr>
						<tr>
							<td><code>multiplepipelines_label</code></td>
							<td><code>"Select a position"</code></td>
							<td>Label displayed above the pipeline dropdown.</td>
						</tr>
					</tbody>
				</table>
				<div class="cinode-code-block" style="margin-top:1rem;">
					<code id="sc-multi">[cinode multiplepipelines="1235:Pipeline 1,1676:Pipeline 2" multiplepipeline_stageid="6003,7783" multiplepipelines_label="Select a position"]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-multi" aria-label="Copy shortcode">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copy
					</button>
				</div>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-media-text" aria-hidden="true"></span> Full Example &mdash; Candidate Mode</h2>
			</div>
			<div class="inside">
				<div class="cinode-code-block">
					<code id="sc-full">[cinode recipient_type="candidate" formtitle="Apply now" pipelineId="0" pipelineStageId="0" recruitmentManagerId="0" teamId="0" companyAddressId="0" recruitmentSourceId="0" campaignCode="" currencyId="1" firstname_label="First Name" lastname_label="Last Name" email_label="Email" phone_label="Phone" message_label="Cover Letter" linkedin_label="LinkedIn Profile" location_label="Location" availableFrom_label="Available from" attachment_label="Upload CV" accept_label="I accept the Privacy Policy" privacy_url="https://example.com/privacy" privacy_error="Please accept the privacy policy to continue." submitbutton_label="Submit Application" cv_required="0" successful-submit-msg="Thank you! We will be in touch soon." unsuccessful-submit-msg="Something went wrong. Please try again." requiredfield_msg="This field is required."]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-full" aria-label="Copy shortcode">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copy
					</button>
				</div>
			</div>
		</div>

		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle"><span class="dashicons dashicons-businessperson" aria-hidden="true"></span> Full Example &mdash; Subcontractor Mode</h2>
			</div>
			<div class="inside">
				<div class="cinode-code-block">
					<code id="sc-full-sub">[cinode recipient_type="subcontractor" formtitle="Join our network" firstname_label="First Name" lastname_label="Last Name" email_label="Email" phone_label="Phone" message_label="Cover Letter" linkedin_label="LinkedIn Profile" attachment_label="Upload CV" accept_label="I accept the Privacy Policy" privacy_url="https://example.com/privacy" privacy_error="Please accept the privacy policy to continue." submitbutton_label="Submit" cv_required="1" parse_cv="1" show_subcontractor_groups="1" subcontractor_group_ids="" subcontractor_group_selection="single" groups_label="Apply to group:" auto_subcontractor_group_ids="" gender_label="Gender" gender_option_male="Male" gender_option_female="Female" gender_option_other="Other / prefer not to say" successful-submit-msg="Thank you! We will be in touch soon." unsuccessful-submit-msg="Something went wrong. Please try again." requiredfield_msg="This field is required."]</code>
					<button type="button" class="button cinode-copy-btn" data-clipboard-target="#sc-full-sub" aria-label="Copy shortcode">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copy
					</button>
				</div>
			</div>
		</div>

	</div><!-- #tab-shortcode -->

	<!-- TAB: Spam Protection (no form) -->
	<div id="tab-spam" class="cinode-tab-content" style="display:none;">
		<div class="postbox cinode-card">
			<div class="postbox-header">
				<h2 class="hndle">
					<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
					Google reCAPTCHA
				</h2>
			</div>
			<div class="inside">
				<p>Protect your recruitment form from spam submissions using Google reCAPTCHA.</p>
				<ol class="cinode-step-list">
					<li>
						Go to the <a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener noreferrer">Google reCAPTCHA Admin Console <span class="dashicons dashicons-external" style="font-size:0.85em;vertical-align:middle;" aria-hidden="true"></span></a> and register your site to obtain a <strong>Site Key</strong> and <strong>Secret Key</strong>.
					</li>
					<li>
						Install the <a href="https://wordpress.org/plugins/advanced-nocaptcha-recaptcha/" target="_blank" rel="noopener noreferrer">CAPTCHA 4WP <span class="dashicons dashicons-external" style="font-size:0.85em;vertical-align:middle;" aria-hidden="true"></span></a> plugin from the WordPress plugin directory.
					</li>
					<li>In the CAPTCHA 4WP settings, enter your <strong>Site Key</strong> and <strong>Secret Key</strong>.</li>
					<li>The Cinode Recruitment form will automatically integrate with reCAPTCHA once the plugin is configured.</li>
				</ol>
			</div>
		</div>
	</div><!-- #tab-spam -->

</div><!-- .cinode-wrap -->
<?php
}
