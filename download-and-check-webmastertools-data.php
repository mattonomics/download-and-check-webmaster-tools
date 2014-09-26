<?php
/**
 * Plugin Name: GovLoop Check URLs
 * Plugin Author: Matt Gross - 10up
 */

if ( class_exists( 'WP_CLI' ) ):

	class download_and_check_webmaster_tools_data extends WP_CLI_COMMAND {
		private $webmastertools;
		private $still_bad = array();
		private $good = 0;

		public function webmaster( $args, $assoc = array() ) {
			list( $action ) = $args;

			if ( empty( $assoc['email'] ) ) {
				WP_CLI::error( __( 'You must provide an email address connected to a Google Webmaster Tools account.', 'dcwd' ) );
			} elseif ( empty( $assoc['password'] ) ) {
				WP_CLI::error( sprintf( __( 'Please provide the password for the Google Webmaster Tools account associated with %s', 'dcwd' ), $assoc['email'] ) );
			} elseif ( $action === 'download-404s' && empty( $assoc['account'] ) ) {
				WP_CLI::error( __( 'Please provide the URL for which you would like to download data.', 'dcwd' ) );
			}

			require_once 'gwtdata.v2.php';

			$this->_sign_in( $assoc['email'], $assoc['password'] );

			if ( $action == 'download-404s' ) {
				$this->_download_table( $assoc['account'] );
			} elseif ( $action == 'get-sites' ) {
				$this->_get_sites();
			}
		}

		private function _download_table( $account = '' ) {
			$directory = trailingslashit( __DIR__ ) . 'csv';
			if ( ! is_writable( $directory ) ) {
				WP_CLI::error( sprintf( __( 'Unable to write to %s. Please check the permissions.', 'gcwd' ), $directory ) );
			}

			if ( empty( $account ) ) {
				WP_CLI::error( __( 'Please supply the URL for which you would like Google Webmaster Tools data.', 'gcwd' ) );
			}

			WP_CLI::log( __( 'Downloading table. Please wait…', 'gcwd' ) );

			if ( $this->webmastertools->getCsv( $account, $directory ) === false ) {
				WP_CLI::error( __( 'Unable to download table.', 'gcwd' ) );
			}

			WP_CLI::success( sprintf( __( 'Crawl Errors downloaded to %s', 'gcwd' ), $directory ) );
		}

		private function _get_sites() {
			$sites = $this->webmastertools->getSites();

			if ( $sites === false || ! is_array( $sites ) ) {
				WP_CLI::error( __( 'Unable to retrieve a list of sites from Google Webmaster Tools.', 'gcwd' ) );
			}

			WP_CLI::log( '' );
			WP_CLI::log( __( 'Available Sites on Google Webmaster Tools', 'gcwd' ) );
			WP_CLI::log( str_repeat( '=', 41 ) );

			$counter = 1;
			foreach ( $sites as $site ) {
				WP_CLI::Log( "{$counter}. " . $site );
				++ $counter;
			}
			WP_CLI::log( '' );
		}

		private function _sign_in( $email, $password ) {
			$this->webmastertools = new GwtCrawlErrors();
			if ( $this->webmastertools->login( $email, $password ) === false ) {
				WP_CLI::error( __( 'Unable to log in to Google Webmaster Tools with the provided username and password.', 'dcwd' ) );
			}
		}

		public function check( $args, $assoc = array() ) {
			$directory = trailingslashit( __DIR__ ) . 'csv';
			if ( ! is_dir( $directory ) || ! file_exists( $directory ) ) {
				WP_CLI::error( __( 'There csv directory is empty. You will need to download a csv file first.', 'gcwd' ) );
			}

			$read = scandir( $directory );
			$file = '';
			foreach ( $read as $red ) {
				if ( strpos( $red, 'gwt-crawlerrors' ) === 0 ) {
					$file = $directory . "/{$red}";
					break;
				}
			}

			if ( ! empty( $assoc['new-url'] ) ) {
				$replace_url = parse_url( $assoc['new-url'] );
				$replace     = $replace_url['scheme'] . '://' . $replace_url['host'];
			}

			WP_CLI::log( sprintf( __( 'Now reading %s…', 'gcwd' ), basename( $file ) ) );
			$contents = array_map( 'str_getcsv', file( $file ) );
			$search   = '';
			$count    = 0;
			foreach ( $contents as $content ) {
				if ( $count > 0 ) {
					if ( empty( $search ) && isset( $replace ) ) {
						$search_url = parse_url( $content[4] );
						$search     = $search_url['scheme'] . '://' . $search_url['host'];
					}
					$url = $content[4];
					if ( ! empty( $replace ) && ! empty( $search ) ) {
						$url = str_replace( $search, $replace, $url );
					}
					$this->_make_call( $url, $count );
				}
				++ $count;
			}
			$this->_write_summary( $count );
			WP_CLI::success( __( 'Analysis complete. Please check the csv/ directory for a bad link list.', 'gcwd' ) );
		}

		private function _make_call( $url, $count ) {
			$headers = get_headers( $url );
			if ( strpos( $headers[0], '301' ) !== false || strpos( $headers[0], '200' ) !== false || strpos( $headers[0], '302' ) !== false ) {
				++ $this->good;
			} else {
				$this->still_bad[] = array(
					'url'    => $url,
					'status' => $headers[0]
				);
			}
		}

		private function _write_summary( $count ) {
			$bad  = count( $this->still_bad );
			$good = $this->good;

			WP_CLI::log( '' );
			WP_CLI::Log( __( '====== Summary ======', 'gcwd' ) );
			WP_CLI::log( sprintf( __( 'Total checked links: %d', 'gcwd' ), $count ) );
			WP_CLI::log( sprintf( __( 'Total bad links: %d', 'gcwd' ), $bad ) );
			WP_CLI::log( sprintf( __( 'Total good links: %d', 'gcwd' ), $good ) );
			WP_CLI::log( sprintf( __( 'Success rate (percent): %d', 'gcwd' ), ( $good / $count ) * 100  ) );
			WP_CLI::log( '' );

			$csv = "URL,\"HTML Header\"\n";

			foreach ( $this->still_bad as $bad_url ) {
				$csv .= "\"{$bad_url['url']}\",\"{$bad_url['status']}\"\n";
			}

			file_put_contents( trailingslashit( __DIR__ ) . 'csv/results.csv', $csv );
		}
	}

	WP_CLI::add_command( 'gtools', 'download_and_check_webmaster_tools_data' );

endif;