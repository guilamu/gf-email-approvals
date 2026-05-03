<?php

defined( 'ABSPATH' ) || exit;

/**
 * Handles WordPress plugin updates from GitHub releases.
 */
class GFEmailApprovalsGitHubUpdater {
	/**
	 * GitHub account hosting the plugin.
	 *
	 * @var string
	 */
	private const GITHUB_USER = 'guilamu';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private const GITHUB_REPO = 'gf-email-approvals';

	/**
	 * Plugin file relative to the plugins directory.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'gf-email-approvals/gf-email-approvals.php';

	/**
	 * Plugin slug used by the WordPress plugin details modal.
	 *
	 * @var string
	 */
	private const PLUGIN_SLUG = 'gf-email-approvals';

	/**
	 * Plugin display name.
	 *
	 * @var string
	 */
	private const PLUGIN_NAME = 'Email Approvals for Gravity Forms';

	/**
	 * Short plugin description used as a fallback.
	 *
	 * @var string
	 */
	private const PLUGIN_DESCRIPTION = 'Adds email-based approval notifications and secure decision links to Gravity Forms entries.';

	/**
	 * Minimum WordPress version required.
	 *
	 * @var string
	 */
	private const REQUIRES_WP = '6.5';

	/**
	 * Highest WordPress version explicitly tested by the release package.
	 *
	 * @var string
	 */
	private const TESTED_WP = '6.8';

	/**
	 * Minimum PHP version required.
	 *
	 * @var string
	 */
	private const REQUIRES_PHP = '7.4';

	/**
	 * Minimum Gravity Forms version required.
	 *
	 * @var string
	 */
	private const REQUIRES_GF = '2.7';

	/**
	 * Translation domain.
	 *
	 * @var string
	 */
	private const TEXT_DOMAIN = 'gf-email-approvals';

	/**
	 * Cache key used for GitHub release data.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'gf_email_approvals_github_release';

	/**
	 * Release cache duration in seconds.
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 43200;

	/**
	 * Optional GitHub access token for private repositories.
	 *
	 * @var string
	 */
	private const GITHUB_TOKEN = '';

	/**
	 * Registers updater hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_for_update' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );
		add_action( 'admin_head', array( __CLASS__, 'plugin_info_css' ) );
	}

	/**
	 * Returns the latest GitHub release data with transient caching.
	 *
	 * @return array|null
	 */
	private static function get_release_data() {
		$release_data = get_transient( self::CACHE_KEY );

		if ( false !== $release_data && is_array( $release_data ) ) {
			return $release_data;
		}

		$args = array(
			'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
			'timeout'    => 15,
			'headers'    => array(),
		);

		if ( '' !== self::GITHUB_TOKEN ) {
			$args['headers']['Authorization'] = 'token ' . self::GITHUB_TOKEN;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO ),
			$args
		);

		if ( is_wp_error( $response ) ) {
			self::log_error( $response->get_error_message() );
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $response_code ) {
			self::log_error( 'HTTP ' . $response_code );
			return null;
		}

		$release_data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release_data['tag_name'] ) ) {
			self::log_error( 'No tag_name in release response.' );
			return null;
		}

		set_transient( self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION );

		return $release_data;
	}

	/**
	 * Returns the preferred release package URL.
	 *
	 * @param array $release_data GitHub release payload.
	 *
	 * @return string
	 */
	private static function get_package_url( $release_data ) {
		if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
			foreach ( $release_data['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) ) {
					continue;
				}

				if ( '.zip' === strtolower( substr( $asset['name'], -4 ) ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		return isset( $release_data['zipball_url'] ) ? (string) $release_data['zipball_url'] : '';
	}

	/**
	 * Adds update information for WordPress core when a new GitHub release exists.
	 *
	 * @param array|false $update      Existing update data.
	 * @param array       $plugin_data Plugin header data.
	 * @param string      $plugin_file Plugin file being checked.
	 * @param array       $locales     Installed locales.
	 *
	 * @return array|false
	 */
	public static function check_for_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( self::PLUGIN_FILE !== $plugin_file ) {
			return $update;
		}

		$release_data = self::get_release_data();

		if ( null === $release_data ) {
			return $update;
		}

		$new_version = ltrim( (string) $release_data['tag_name'], 'v' );
		$current     = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '0.0.0';

		if ( version_compare( $current, $new_version, '>=' ) ) {
			return $update;
		}

		return array(
			'id'            => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'slug'          => self::PLUGIN_SLUG,
			'plugin'        => self::PLUGIN_FILE,
			'new_version'   => $new_version,
			'version'       => $new_version,
			'package'       => self::get_package_url( $release_data ),
			'url'           => isset( $release_data['html_url'] ) ? $release_data['html_url'] : '',
			'tested'        => self::TESTED_WP,
			'requires'      => self::REQUIRES_WP,
			'requires_php'  => self::REQUIRES_PHP,
			'compatibility' => new stdClass(),
			'icons'         => array(),
			'banners'       => array(),
		);
	}

	/**
	 * Returns plugin information for the WordPress details modal.
	 *
	 * @param false|object|array $res    Existing result.
	 * @param string             $action Requested plugin API action.
	 * @param object             $args   Plugin API arguments.
	 *
	 * @return false|object|array
	 */
	public static function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $res;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file  = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
		$plugin_data  = get_plugin_data( $plugin_file, false, false );
		$release_data = self::get_release_data();
		$version      = $release_data ? ltrim( (string) $release_data['tag_name'], 'v' ) : ( isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : GF_EMAIL_APPROVALS_VERSION );
		$readme       = self::parse_readme();

		$res                = new stdClass();
		$res->name          = self::PLUGIN_NAME;
		$res->slug          = self::PLUGIN_SLUG;
		$res->plugin        = self::PLUGIN_FILE;
		$res->version       = $version;
		$res->author        = sprintf( '<a href="https://github.com/%1$s">%1$s</a>', esc_html( self::GITHUB_USER ) );
		$res->homepage      = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
		$res->requires      = self::REQUIRES_WP;
		$res->tested        = get_bloginfo( 'version' );
		$res->requires_php  = self::REQUIRES_PHP;
		$res->download_link = $release_data ? self::get_package_url( $release_data ) : '';
		$res->last_updated  = $release_data && ! empty( $release_data['published_at'] ) ? $release_data['published_at'] : '';

		$res->sections = array(
			'description' => ! empty( $readme['description'] )
				? $readme['description']
				: '<p>' . esc_html( self::PLUGIN_DESCRIPTION ) . '</p>',
		);

		if ( ! empty( $readme['installation'] ) ) {
			$res->sections['installation'] = $readme['installation'];
		}

		if ( ! empty( $readme['faq'] ) ) {
			$res->sections['faq'] = $readme['faq'];
		}

		$changelog_html    = '';
		$installed_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '0.0.0';

		if ( $release_data && ! empty( $release_data['body'] ) && version_compare( $installed_version, $version, '<' ) ) {
			$changelog_html .= '<h4>' . esc_html( $version ) . '</h4>' . self::markdown_to_html( $release_data['body'] );
		}

		if ( ! empty( $readme['changelog'] ) ) {
			$changelog_html .= $readme['changelog'];
		}

		$res->sections['changelog'] = ! empty( $changelog_html )
			? $changelog_html
			: sprintf(
				__( '<p>See <a href="https://github.com/%1$s/%2$s/releases" target="_blank" rel="noopener noreferrer">GitHub releases</a> for changelog.</p>', self::TEXT_DOMAIN ),
				esc_attr( self::GITHUB_USER ),
				esc_attr( self::GITHUB_REPO )
			);

		return $res;
	}

	/**
	 * Outputs CSS and JS for the plugin information modal.
	 *
	 * @return void
	 */
	public static function plugin_info_css() {
		if ( ! isset( $_GET['plugin'], $_GET['tab'] ) ) {
			return;
		}

		$tab    = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );

		if ( 'plugin-information' !== $tab || self::PLUGIN_SLUG !== $plugin ) {
			return;
		}

		$requires_gf_html = sprintf(
			'<strong>%1$s</strong> %2$s',
			esc_html__( 'Requires Gravity Forms:', self::TEXT_DOMAIN ),
			esc_html( sprintf( __( '%s or higher', self::TEXT_DOMAIN ), self::REQUIRES_GF ) )
		);

		echo '<style>'
			. '#plugin-information-title.with-banner {'
			. '--s:27px;--c1:#b2b2b2;--c2:#ffffff;--c3:#d9d9d9;--_g:var(--c3) 0 120deg,#0000 0;'
			. 'background:conic-gradient(from -60deg at 50% calc(100%/3),var(--_g)),conic-gradient(from 120deg at 50% calc(200%/3),var(--_g)),conic-gradient(from 60deg at calc(200%/3),var(--c3) 60deg,var(--c2) 0 120deg,#0000 0),conic-gradient(from 180deg at calc(100%/3),var(--c1) 60deg,var(--_g)),linear-gradient(90deg,var(--c1) calc(100%/6),var(--c2) 0 50%,var(--c1) 0 calc(500%/6),var(--c2) 0) !important;'
			. 'background-size:calc(1.732 * var(--s)) var(--s) !important;'
			. '}'
			. '#plugin-information-title.with-banner h2 {'
			. 'position:relative;display:inline-block;max-width:100%;padding:0 15px;margin-top:174px;color:#fff;background:rgba(29,35,39,.9);text-shadow:0 1px 3px rgba(0,0,0,.4);box-shadow:0 0 30px rgba(255,255,255,.1);border-radius:8px;font-size:30px;line-height:1.68;box-sizing:border-box;'
			. '}'
			. '#section-holder .section h2{margin:1.5em 0 .5em;clear:none;}'
			. '#section-holder .section h3{margin:1.5em 0 .5em;}'
			. '#section-holder .section>:first-child{margin-top:0;}'
			. '.md-table{display:table;width:100%;border-collapse:collapse;margin:1em 0;font-size:13px;}'
			. '.md-tr{display:table-row;}'
			. '.md-tr>span{display:table-cell;padding:6px 10px;border:1px solid #ddd;vertical-align:top;}'
			. '.md-th>span{font-weight:600;background:#f5f5f5;}'
			. '</style>';

		echo '<script>'
			. 'document.addEventListener("DOMContentLoaded",function(){'
			. 'var title=document.getElementById("plugin-information-title");'
			. 'if(title){title.classList.add("with-banner");}'
			. 'var items=document.querySelectorAll(".fyi ul li");'
			. 'var phpLine=null;'
			. 'for(var i=0;i<items.length;i++){if(items[i].textContent.indexOf("Requires PHP")!==-1){phpLine=items[i];break;}}'
			. 'if(phpLine&&phpLine.parentNode){var li=document.createElement("li");li.innerHTML=' . wp_json_encode( $requires_gf_html ) . ';phpLine.parentNode.insertBefore(li,phpLine.nextSibling);}'
			. '});'
			. '</script>';
	}

	/**
	 * Parses the local README.md into the sections used by the modal.
	 *
	 * @return array
	 */
	private static function parse_readme() {
		$readme_path = WP_PLUGIN_DIR . '/' . dirname( self::PLUGIN_FILE ) . '/README.md';

		if ( ! file_exists( $readme_path ) || ! is_readable( $readme_path ) ) {
			return array();
		}

		$content = file_get_contents( $readme_path );

		if ( false === $content ) {
			return array();
		}

		$content = preg_replace( '/^#\s+[^\n]+\n*/m', '', $content, 1 );

		$utility_sections = array(
			'changelog',
			'requirements',
			'installation',
			'faq',
			'project structure',
			'acknowledgements',
			'license',
		);

		$parts        = preg_split( '/^##\s+/m', $content );
		$description  = trim( isset( $parts[0] ) ? $parts[0] : '' );
		$installation = '';
		$faq          = '';
		$changelog    = '';

		for ( $index = 1, $count = count( $parts ); $index < $count; $index++ ) {
			$lines = explode( "\n", $parts[ $index ], 2 );
			$title = strtolower( trim( $lines[0] ) );
			$body  = trim( isset( $lines[1] ) ? $lines[1] : '' );

			if ( 'installation' === $title ) {
				$installation .= $body . "\n\n";
			} elseif ( 'faq' === $title ) {
				$faq .= $body . "\n\n";
			} elseif ( 'changelog' === $title ) {
				$changelog .= $body . "\n\n";
			} elseif ( ! in_array( $title, $utility_sections, true ) ) {
				$description .= "\n\n## " . trim( $lines[0] ) . "\n" . $body;
			}
		}

		return array(
			'description'  => self::markdown_to_html( trim( $description ) ),
			'installation' => self::markdown_to_html( trim( $installation ) ),
			'faq'          => self::markdown_to_html( trim( $faq ) ),
			'changelog'    => self::markdown_to_html( trim( $changelog ) ),
		);
	}

	/**
	 * Converts README markdown to safe HTML for the modal.
	 *
	 * @param string $markdown Markdown content.
	 *
	 * @return string
	 */
	private static function markdown_to_html( $markdown ) {
		if ( '' === $markdown ) {
			return '';
		}

		$markdown = preg_replace( '/!\[[^\]]*\]\([^\)]+\)/', '', $markdown );

		if ( ! class_exists( 'Parsedown' ) ) {
			require_once __DIR__ . '/Parsedown.php';
		}

		$parsedown = new Parsedown();
		$parsedown->setSafeMode( true );

		return self::tables_to_divs( $parsedown->text( $markdown ) );
	}

	/**
	 * Rewrites HTML tables into div/span markup that survives wp_kses.
	 *
	 * @param string $html Parsed HTML.
	 *
	 * @return string
	 */
	private static function tables_to_divs( $html ) {
		return preg_replace_callback(
			'/<table>(.*?)<\/table>/s',
			function ( $matches ) {
				$table_html = $matches[1];
				$output     = '<div class="md-table">';

				preg_match_all( '/<tr>(.*?)<\/tr>/s', $table_html, $rows );

				foreach ( $rows[1] as $index => $row_content ) {
					$is_header = 0 === $index && false !== strpos( $table_html, '<thead>' );
					$row_class = $is_header ? 'md-tr md-th' : 'md-tr';

					preg_match_all( '/<t[hd](?: [^>]*)?>(.*?)<\/t[hd]>/s', $row_content, $cells );

					$output .= '<div class="' . esc_attr( $row_class ) . '">';

					foreach ( $cells[1] as $cell ) {
						$output .= '<span>' . $cell . '</span>';
					}

					$output .= '</div>';
				}

				$output .= '</div>';

				return $output;
			},
			$html
		);
	}

	/**
	 * Fixes the folder name of GitHub-generated zip packages during update.
	 *
	 * @param string      $source        Extracted source path.
	 * @param string      $remote_source Temporary remote source path.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $hook_extra    Upgrader metadata.
	 *
	 * @return string|WP_Error
	 */
	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		unset( $upgrader );

		if ( empty( $hook_extra['plugin'] ) || self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $source;
		}

		$correct_folder = dirname( self::PLUGIN_FILE );
		$source_folder  = basename( untrailingslashit( $source ) );

		if ( $source_folder === $correct_folder ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . $correct_folder . '/';

		if ( $wp_filesystem && $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		if ( $wp_filesystem && $wp_filesystem->copy( $source, $new_source, true ) && $wp_filesystem->delete( $source, true ) ) {
			return $new_source;
		}

		self::log_error( sprintf( 'Failed to rename update folder from %s to %s', $source, $new_source ) );

		return new WP_Error(
			'rename_failed',
			__( 'Unable to rename the update folder. Please retry or update manually.', self::TEXT_DOMAIN )
		);
	}

	/**
	 * Logs debug information only when WordPress debug mode is enabled.
	 *
	 * @param string $message Message to log.
	 *
	 * @return void
	 */
	private static function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( self::PLUGIN_NAME . ' updater: ' . $message );
		}
	}
}

GFEmailApprovalsGitHubUpdater::init();