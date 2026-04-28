<?php
/**
 * FRM hook profiler utilities.
 *
 * Lightweight runtime profiler for selected WordPress hooks.
 *
 * Why this exists:
 * - Determine which callbacks are actually firing during a request.
 * - Attribute calls to plugin/theme/core code paths.
 * - Capture cheap timing + memory snapshots without external tooling.
 *
 * Output format:
 * - JSON lines in wp-content/uploads/frm-hook-profile.log
 * - One line per profiled callback execution.
 *
 * Safety notes:
 * - Intended for short-lived diagnostics, not permanent production logging.
 * - Uses file appends on every sampled callback, so disable when done.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FRM_HOOK_PROFILER' ) ) {
	define( 'FRM_HOOK_PROFILER', true );
}

/**
 * When true, wrap all callbacks on selected hooks (plugin/theme/core),
 * not only callbacks explicitly wrapped with frm_profile_callable().
 */
if ( ! defined( 'FRM_HOOK_PROFILER_WRAP_ALL_CALLBACKS' ) ) {
	define( 'FRM_HOOK_PROFILER_WRAP_ALL_CALLBACKS', true );
}

if ( ! function_exists( 'frm_profiler_log_path' ) ) {
	/**
	 * Resolve destination file for profiler rows.
	 *
	 * Uses uploads dir when available so logs survive theme updates.
	 * Falls back to wp-content when uploads metadata is unavailable.
	 *
	 * @return string
	 */
	function frm_profiler_log_path() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return WP_CONTENT_DIR . '/frm-hook-profile.log';
		}
		return trailingslashit( $upload['basedir'] ) . 'frm-hook-profile.log';
	}
}

if ( ! function_exists( 'frm_profiler_write' ) ) {
	/**
	 * Append one profiler row to log as JSONL.
	 *
	 * @param array $row Associative data written as one JSON line.
	 * @return void
	 */
	function frm_profiler_write( $row ) {
		$line = wp_json_encode( $row, JSON_UNESCAPED_SLASHES ) . PHP_EOL;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( frm_profiler_log_path(), $line, FILE_APPEND );
	}
}

if ( ! function_exists( 'frm_profiler_callback_id' ) ) {
	/**
	 * Build a stable textual callback identifier for labels/grouping.
	 *
	 * @param callable $callback Callback to identify.
	 * @return string
	 */
	function frm_profiler_callback_id( $callback ) {
		if ( is_string( $callback ) ) {
			return 'function:' . $callback;
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$target = is_object( $callback[0] ) ? get_class( $callback[0] ) . '#' . spl_object_hash( $callback[0] ) : (string) $callback[0];
			return 'method:' . $target . '::' . (string) $callback[1];
		}

		if ( $callback instanceof Closure ) {
			return 'closure:' . spl_object_hash( $callback );
		}

		if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
			return 'invokable:' . get_class( $callback ) . '#' . spl_object_hash( $callback );
		}

		return 'unknown:' . md5( wp_json_encode( $callback ) );
	}
}

if ( ! function_exists( 'frm_profiler_reflection_source' ) ) {
	/**
	 * Resolve callback file path and high-level ownership.
	 *
	 * Ownership buckets are intentionally coarse:
	 * - plugin: callback file under wp-content/plugins
	 * - theme: callback file under wp-content/themes
	 * - core: callback file under wp-includes/wp-admin
	 * - core/unknown: unable to resolve (or non-file callback)
	 *
	 * @param callable $callback Callback to inspect.
	 * @return array{source:string, source_file:string}
	 */
	function frm_profiler_reflection_source( $callback ) {
		$file   = '';
		$source = 'core/unknown';

		try {
			if ( is_string( $callback ) && function_exists( $callback ) ) {
				$ref  = new ReflectionFunction( $callback );
				$file = (string) $ref->getFileName();
			} elseif ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
				$ref  = new ReflectionMethod( $callback[0], $callback[1] );
				$file = (string) $ref->getFileName();
			} elseif ( $callback instanceof Closure ) {
				$ref  = new ReflectionFunction( $callback );
				$file = (string) $ref->getFileName();
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$ref  = new ReflectionMethod( $callback, '__invoke' );
				$file = (string) $ref->getFileName();
			}
		} catch ( Throwable $e ) {
			$file   = '';
			$source = 'core/unknown';
		}

		if ( $file ) {
			$normalized = wp_normalize_path( $file );
			if ( false !== strpos( $normalized, '/wp-content/plugins/' ) ) {
				$source = 'plugin';
			} elseif ( false !== strpos( $normalized, '/wp-content/themes/' ) ) {
				$source = 'theme';
			} elseif ( false !== strpos( $normalized, '/wp-includes/' ) || false !== strpos( $normalized, '/wp-admin/' ) ) {
				$source = 'core';
			}
		}

		return array(
			'source'      => $source,
			'source_file' => $file,
		);
	}
}

if ( ! function_exists( 'frm_profile_callable' ) ) {
	/**
	 * Wrap a callback and emit one row per invocation.
	 *
	 * This is the opt-in wrapper used by explicit theme callbacks.
	 * For global wrapping on selected hooks, see frm_profiler_wrap_hook_callbacks().
	 *
	 * @param string   $label    Identifier written to log.
	 * @param callable $callback Original callback to profile.
	 * @return callable
	 */
	function frm_profile_callable( $label, $callback ) {
		$meta = frm_profiler_reflection_source( $callback );

		return function( ...$args ) use ( $label, $callback, $meta ) {
			if ( ! FRM_HOOK_PROFILER ) {
				return $callback( ...$args );
			}

			$start_time = microtime( true );
			$start_mem  = memory_get_usage( true );
			$ok         = true;
			$error_msg  = null;

			try {
				$result = $callback( ...$args );
			} catch ( Throwable $e ) {
				$ok        = false;
				$error_msg = $e->getMessage();
				throw $e;
			} finally {
				$elapsed_ms = ( microtime( true ) - $start_time ) * 1000;
				$mem_delta  = memory_get_usage( true ) - $start_mem;

				frm_profiler_write(
					array(
						'ts'          => gmdate( 'c' ),
						'url'         => isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '',
						'hook'        => current_filter(),
						'label'       => $label,
						'source'      => $meta['source'],
						'source_file' => $meta['source_file'],
						'elapsed_ms'  => round( $elapsed_ms, 3 ),
						'mem_delta_b' => $mem_delta,
						'peak_mem_b'  => memory_get_peak_usage( true ),
						'ok'          => $ok,
						'error'       => $error_msg,
					)
				);
			}

			return $result;
		};
	}
}

if ( ! function_exists( 'frm_profiler_wrap_hook_callbacks' ) ) {
	/**
	 * Wrap every callback currently attached to a hook.
	 *
	 * Important behavior:
	 * - Snapshot-based: wraps callbacks currently registered at wrap time.
	 * - Late-added callbacks are not wrapped unless this runs again.
	 * - Preserves execution order/priority; only swaps function reference.
	 *
	 * @param string $hook_name Hook to wrap.
	 * @return void
	 */
	function frm_profiler_wrap_hook_callbacks( $hook_name ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook_name ] ) || ! ( $wp_filter[ $hook_name ] instanceof WP_Hook ) ) {
			return;
		}

		$hook = $wp_filter[ $hook_name ];

		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $idx => $callback_data ) {
				if ( empty( $callback_data['function'] ) ) {
					continue;
				}

				$original = $callback_data['function'];
				$orig_id  = frm_profiler_callback_id( $original );

				// Skip already wrapped callbacks.
				if ( is_array( $original ) && isset( $original[1] ) && '__frm_profile_wrapped' === $original[1] ) {
					continue;
				}

				$label = 'hook:' . $hook_name . ':' . $orig_id;
				$wrap  = new class( $label, $original ) {
					private $label;
					private $callback;

					public function __construct( $label, $callback ) {
						$this->label    = $label;
						$this->callback = $callback;
					}

					public function __frm_profile_wrapped( ...$args ) {
						// Resolve source on each call so callback provenance is explicit in logs.
						$meta = frm_profiler_reflection_source( $this->callback );
						$start_time = microtime( true );
						$start_mem  = memory_get_usage( true );
						$ok         = true;
						$error_msg  = null;

						try {
							return call_user_func_array( $this->callback, $args );
						} catch ( Throwable $e ) {
							$ok        = false;
							$error_msg = $e->getMessage();
							throw $e;
						} finally {
							frm_profiler_write(
								array(
									'ts'          => gmdate( 'c' ),
									'url'         => isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '',
									'hook'        => current_filter(),
									'label'       => $this->label,
									'source'      => $meta['source'],
									'source_file' => $meta['source_file'],
									'elapsed_ms'  => round( ( microtime( true ) - $start_time ) * 1000, 3 ),
									'mem_delta_b' => memory_get_usage( true ) - $start_mem,
									'peak_mem_b'  => memory_get_peak_usage( true ),
									'ok'          => $ok,
									'error'       => $error_msg,
								)
							);
						}
					}
				};

				$hook->callbacks[ $priority ][ $idx ]['function'] = array( $wrap, '__frm_profile_wrapped' );
			}
		}
	}
}

if ( ! function_exists( 'frm_profiler_bootstrap_wraps' ) ) {
	/**
	 * Wrap high-interest callbacks after plugins/theme register their hooks.
	 *
	 * Runs very late on wp_loaded so most plugin/theme callbacks already exist.
	 * Current scope is intentionally narrow to keep log volume manageable.
	 *
	 * @return void
	 */
	function frm_profiler_bootstrap_wraps() {
		if ( ! FRM_HOOK_PROFILER || ! FRM_HOOK_PROFILER_WRAP_ALL_CALLBACKS ) {
			return;
		}

		frm_profiler_wrap_hook_callbacks( 'pre_get_posts' );
		frm_profiler_wrap_hook_callbacks( 'query_loop_block_query_vars' );
	}
}
add_action( 'wp_loaded', 'frm_profiler_bootstrap_wraps', 9999 );
