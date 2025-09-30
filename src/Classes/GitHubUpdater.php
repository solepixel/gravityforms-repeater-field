<?php
/**
 * GitHub Updater for Gravity Forms Repeater Field
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.1
 */

namespace GravityFormsRepeaterField;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GitHubUpdater
 *
 * Handles automatic updates from GitHub releases for this plugin.
 *
 * @since 1.0.1
 */
class GitHubUpdater {

	/**
	 * Full path to the plugin bootstrap file.
	 *
	 * @var string
	 */
	private string $pluginFile;

	/**
	 * GitHub username/organization.
	 *
	 * @var string
	 */
	private string $githubUser;

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private string $githubRepo;

	/**
	 * Optional GitHub token for private repos or higher rate limits.
	 *
	 * @var string
	 */
	private string $githubToken;

	/**
	 * Asset filename prefix to look for in GitHub release assets.
	 * If provided, the updater prefers an asset zip starting with this prefix.
	 *
	 * @var string
	 */
	private string $assetPrefix;

	/**
	 * Cached plugin header data.
	 *
	 * @var array<string,mixed>
	 */
	private array $pluginData = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.1
	 *
	 * @param string $pluginFile  Path to plugin main file.
	 * @param string $githubUser  GitHub username/org.
	 * @param string $githubRepo  GitHub repository name.
	 * @param string $assetPrefix Optional asset name prefix to prefer.
	 * @param string $githubToken Optional token for auth.
	 */
	public function __construct( string $pluginFile, string $githubUser, string $githubRepo, string $assetPrefix = '', string $githubToken = '' ) {
		$this->pluginFile  = $pluginFile;
		$this->githubUser  = $githubUser;
		$this->githubRepo  = $githubRepo;
		$this->assetPrefix = $assetPrefix;
		$this->githubToken = $githubToken;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'upgrader_pre_download' ), 10, 2 );
	}

	/**
	 * Check for updates against the latest GitHub release.
	 *
	 * @param object $transient WordPress transient with update data.
	 * @return object
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->pluginData    = get_plugin_data( $this->pluginFile );
		$currentVersion      = (string) ( $this->pluginData['Version'] ?? '0.0.0' );
		$latestRelease       = $this->get_latest_release();
		if ( ! $latestRelease ) {
			return $transient;
		}

		$latestVersion = ltrim( (string) ( $latestRelease['tag_name'] ?? '0.0.0' ), 'v' );

		if ( version_compare( $currentVersion, $latestVersion, '<' ) ) {
			$transient->response[ plugin_basename( $this->pluginFile ) ] = (object) array(
				'slug'          => dirname( plugin_basename( $this->pluginFile ) ),
				'plugin'        => plugin_basename( $this->pluginFile ),
				'new_version'   => $latestVersion,
				'url'           => $this->pluginData['PluginURI'] ?? '',
				'package'       => $this->get_download_url( $latestRelease ),
				'icons'         => array(),
				'banners'       => array(),
				'banners_rtl'   => array(),
				'tested'        => $this->pluginData['TestedUpTo'] ?? '',
				'requires_php'  => $this->pluginData['RequiresPHP'] ?? '',
				'compatibility' => new \stdClass(),
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the Update UI.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action Action being requested.
	 * @param object $args   Arguments (expects ->slug).
	 * @return mixed
	 */
	public function plugin_api_call( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( plugin_basename( $this->pluginFile ) ) !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$latestVersion = ltrim( (string) ( $release['tag_name'] ?? '0.0.0' ), 'v' );

		$info                 = new \stdClass();
		$info->name           = $this->pluginData['Name'] ?? 'Gravity Forms Repeater Field Add-on';
		$info->slug           = $args->slug;
		$info->version        = $latestVersion;
		$info->author         = $this->pluginData['Author'] ?? '';
		$info->author_profile = $this->pluginData['AuthorURI'] ?? '';
		$info->homepage       = $this->pluginData['PluginURI'] ?? '';
		$info->requires       = $this->pluginData['RequiresAtLeast'] ?? '';
		$info->tested         = $this->pluginData['TestedUpTo'] ?? '';
		$info->requires_php   = $this->pluginData['RequiresPHP'] ?? '';
		$info->last_updated   = $release['published_at'] ?? '';
		$info->sections       = array(
			'description' => $this->pluginData['Description'] ?? '',
			'changelog'   => $this->format_changelog( $release ),
		);
		$info->download_link  = $this->get_download_url( $release );

		return $info;
	}

	/**
	 * Get latest release metadata from GitHub.
	 *
	 * @return array<string,mixed>|false
	 */
	private function get_latest_release() {
		$cacheKey = sprintf( 'gfrf_github_latest_release_%s_%s', md5( $this->githubUser ), md5( $this->githubRepo ) );
		$cached   = get_transient( $cacheKey );
		if ( false !== $cached ) {
			return $cached;
		}

		$apiUrl = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			rawurlencode( $this->githubUser ),
			rawurlencode( $this->githubRepo )
		);

		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress-Plugin-Update-Checker',
		);

		if ( ! empty( $this->githubToken ) ) {
			$headers['Authorization'] = 'token ' . $this->githubToken;
		}

		$response = wp_remote_get(
			$apiUrl,
			array(
				'headers' => $headers,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body    = (string) wp_remote_retrieve_body( $response );
		$release = json_decode( $body, true );

		if ( ! is_array( $release ) || ! isset( $release['tag_name'] ) ) {
			return false;
		}

		set_transient( $cacheKey, $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Prefer a release asset zip that matches the assetPrefix if available, otherwise fall back to zipball.
	 *
	 * @param array<string,mixed> $release Release metadata.
	 * @return string
	 */
	private function get_download_url( array $release ): string {
		if ( isset( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				$name = (string) ( $asset['name'] ?? '' );
				$url  = (string) ( $asset['browser_download_url'] ?? '' );
				if ( '' !== $this->assetPrefix && '' !== $name && strpos( $name, $this->assetPrefix . '-' ) === 0 && substr( $name, -4 ) === '.zip' ) {
					return $url;
				}
				// Also accept repo-name prefixed zips.
				if ( '' === $this->assetPrefix && '' !== $name && strpos( $name, $this->githubRepo . '-' ) === 0 && substr( $name, -4 ) === '.zip' ) {
					return $url;
				}
			}
		}

		return (string) ( $release['zipball_url'] ?? '' );
	}

	/**
	 * Format changelog using the release body if present.
	 *
	 * @param array<string,mixed> $release Release metadata.
	 * @return string
	 */
	private function format_changelog( array $release ): string {
		$body = (string) ( $release['body'] ?? '' );
		return '' !== $body ? $body : 'No changelog available for this release.';
	}

	/**
	 * Ensure authenticated downloads for private GitHub repos.
	 *
	 * @param bool   $reply   Whether to bail without returning the package.
	 * @param string $package Package URL.
	 * @return bool|\WP_Error
	 */
	public function upgrader_pre_download( $reply, $package ) {
		if ( strpos( (string) $package, 'api.github.com' ) === false ) {
			return $reply;
		}

		if ( empty( $this->githubToken ) ) {
			return $reply;
		}

		add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 10, 2 );

		return $reply;
	}

	/**
	 * Add Authorization header to GitHub API requests when a token is provided.
	 *
	 * @param array<string,mixed> $args Request args.
	 * @param string              $url  Request URL.
	 * @return array<string,mixed>
	 */
	public function add_auth_header( array $args, string $url ): array {
		if ( strpos( $url, 'api.github.com' ) !== false && ! empty( $this->githubToken ) ) {
			if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
				$args['headers'] = array();
			}
			$args['headers']['Authorization'] = 'token ' . $this->githubToken;
		}

		return $args;
	}
}


