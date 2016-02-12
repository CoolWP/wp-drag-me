<?php
/**
 * @author: suifengtec coolwp.com
 * @date:   2016-02-12 02:05:23
 * @last Modified by:   suifengtec coolwp.com
 * @last Modified time: 2016-02-12 14:57:10
 */
/**
 * Plugin Name: WP Drag Me
 * Plugin URI: http://coolwp.com/wp-drag-me.html
 * Description: Description.
 * Version: 0.9.0
 * Author: suifengtec
 * Author URI:  http://coolwp.com
 * Author Email: support@coolwp.com
 * Requires at least: WP 3.8
 * Tested up to: WP 4.4
 * Text Domain: cwp
 * Domain Path: /languages
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

if (!class_exists('WP_Drag_Me')) {

	final class WP_Drag_Me {

		protected static $_instance = null;
		protected static $enable_signup_dragme = true;
		protected static $enable_comment_dragme = true;

		public static function instance() {
			if (is_null(self::$_instance)) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __clone() {}
		public function __wakeup() {}

		public function __construct() {

			if (!wp_is_mobile()) {

				if (self::$enable_signup_dragme || self::$enable_comment_dragme) {
					add_action('wp_ajax_coolwp_drag_me_action', array($this, 'coolwp_drag_me_action'));
					add_action('wp_ajax_nopriv_coolwp_drag_me_action', array($this, 'coolwp_drag_me_action'));
					add_action('init', array(__CLASS__, 'myStartSession'), 1);
					add_action('wp_logout', array(__CLASS__, 'myEndSession'));
					add_action('wp_login', array(__CLASS__, 'myEndSession'));
					add_action('login_head', array(__CLASS__, 'myStartSession'));
				}
				if (self::$enable_signup_dragme) {
					add_action('register_form', array(__CLASS__, 'output_html'));
					add_action('login_enqueue_scripts', array(__CLASS__, 'login_enqueue_scripts'), 1);
					add_filter('shake_error_codes', array(__CLASS__, 'shake_error_codes'), 10, 1);
					add_filter('registration_errors', array(__CLASS__, 'registration_errors'), 10, 3);
				}
				if (self::$enable_comment_dragme) {
					add_action('wp_footer', array(__CLASS__, 'output_html'));
					add_action('preprocess_comment', array($this, 'preprocess_comment'));
					add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));

				}
			}
		}
		public static function myStartSession() {
			if (!session_id() && !is_user_logged_in()) {
				session_start();
			}
		}
		public static function myEndSession() {
			session_destroy();
		}
		public static function registration_errors($errors, $sanitized_user_login, $user_email) {

			if (!self::$enable_signup_dragme) {
				return $errors;
			}
			if (!isset($_SESSION['wp_drag_me']) || !$_SESSION['wp_drag_me']) {

				$errors->add('dragme_error', '<strong>ERROR</strong>: 请拖动下面的滑块进行人机验证。');
			} else {
				if (self::get_ip() != $_SESSION['wp_drag_me']) {
					$errors->add('dragme_error', '<strong>ERROR</strong>: 未通过人机验证。');
				}
				unset($_SESSION['wp_drag_me']);
			}

			return $errors;

		}

		public static function shake_error_codes($error_codes) {
			$error_codes[] = 'dragme_error';
			return $error_codes;
		}

		public static function login_enqueue_scripts() {
			wp_enqueue_script('jquery');
			wp_enqueue_script('jqdragme-js', plugins_url('bower_components/jqdragme/jquery.coolwpdrgme.js', __FILE__), array('jquery'));

		}

		public static function output_html() {
			$is_for_comment = self::is_for_comment();
			$ref = $is_for_comment ? 'comment' : 'register';
			if (!$is_for_comment): ?>
            <div id="coolwp-drag-me"></div>
            <?php endif;?>
            <script>
            jQuery(document).ready(function($){
                <?php if ($is_for_comment): ?>
                    var cwp_dragme_container = document.createElement("div");
                    cwp_dragme_container.className="coolwp-drag-me";
                    cwp_dragme_container.setAttribute("id","coolwp-drag-me");
                    var tagIDComment=document.getElementById("comment");
                    if(tagIDComment){
                        tagIDComment.parentNode.insertBefore(cwp_dragme_container,tagIDComment);
                    }else{
                        var allTagP = document.getElementsByTagName("p");
                        for(var p=0;p<allTagP.length;p++){
                            var allTagTA = allTagP[p].getElementsByTagName("textarea");
                            if(allTagTA.length>0){
                                allTagP[p].parentNode.insertBefore(cwp_dragme_container,allTagP[p]);
                            }
                        }
                    }
                <?php endif;?>
                $('#coolwp-drag-me').coolwpDragMe({
                    tip:            '将滑块拖动到右侧进行人机验证',
                    successTip:     '验证成功！',
                    callback:       function(res){
                                        if(true===res){
                                            $.ajax({
                                                url: '<?php echo self::get_ajax_url(); ?>',
                                                type: "POST",
                                                    dataType: "json",
                                               data: {
                                                    action      : 'coolwp_drag_me_action',
                                                    token       : '<?php echo wp_create_nonce('coolwp_drag_me_action'); ?>',
                                                    user_symbol : '<?php echo self::get_user_symbol(); ?>',
                                                    ref         : '<?php echo $ref; ?>'

                                                },
                                                success:function(resp){
                                                    if ( window.console && window.console.log ) {
                                                      window.console.log( resp );
                                                    }
                                                }

                                            }).fail(function (resp) {
                                                    if ( window.console && window.console.log ) {
                                                        window.console.log( resp );
                                                    }
                                                }).done(function (resp) {});

                                                return false;

                                        }else{

                                           // window.location.href='http://coolwp.com/create-jquery-plugin-3.html';
                                       }

                                    }
                });

        });

        </script>
        <?php

		}

		/**
		 * AJAX Action
		 * @return [type] [description]
		 */
		public function coolwp_drag_me_action() {

			if (
				!isset($_POST['token']) || empty($_POST['token'])
				|| !isset($_POST['user_symbol']) || !$_POST['user_symbol']
			) {
				$r = 'error';
				wp_send_json_error($r);
			}

			$ip = base64_decode(wp_unslash($_POST['user_symbol']));
			$_SESSION['wp_drag_me'] = $ip;
			wp_send_json_success($_SESSION['wp_drag_me']);
		}

		public static function is_for_comment() {

			if (in_array($GLOBALS['pagenow'], array('wp-register.php'))) {
				return false;
			}
/*
global $post;
|| comments_open($post->ID)
 */
			if (is_admin() || is_user_logged_in() || !is_singular()) {
				return false;
			}

			return true;
		}
		public static function get_user_symbol() {

			$ip = self::get_ip();
			if (!$ip) {
				return false;
			}
			$hash = base64_encode($ip);
			/*$hash = sha1($ip);*/

			/*	$_SESSION['wp_drag_me'] = $ip;*/

			return $hash;

		}
		public static function get_ajax_url() {
			$scheme = defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN ? 'https' : 'admin';
			$ajax_url = admin_url('admin-ajax.php', $scheme);
			return $ajax_url;
		}
		public static function get_ip() {

			$ip = false;
			if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
				$ip = $_SERVER["HTTP_CLIENT_IP"];
			}
			if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
				if ($ip) {
					array_unshift($ips, $ip);
					$ip = FALSE;}
				for ($i = 0; $i < count($ips); $i++) {
					if (!eregi("^(10|172\.16|192\.168)\.", $ips[$i])) {
						$ip = $ips[$i];
						break;
					}
				}
			}
			return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
		}

		public function wp_enqueue_scripts() {

			if (self::is_for_comment()) {

				self::login_enqueue_scripts();
			}

		}

		public function preprocess_comment($comment) {

			if (is_user_logged_in()) {
				return $comment;
			}

			if (isset($_SESSION['wp_drag_me']) && $_SESSION['wp_drag_me']) {
				unset($_SESSION['wp_drag_me']);
				return $comment;
			} else {
				if (isset($_POST['isajaxtype']) && $_POST['isajaxtype'] > -1) {

					die("请滑动滚动条解锁1");
				} else {
					wp_die("请滑动滚动条解锁2");

				}

			}

		}

	} /*//CLASS*/
	$GLOBALS['WP_Drag_Me'] = WP_Drag_Me::instance();

}
/*
Okay, You can  code your awesome plugin now!
 */
add_action('init', 'fdfghreyhreuhj');
function fdfghreyhreuhj() {
/*	$a = get_transient('cwp_dragme_127001');

 */
/*	$key = '123456';
$user_login = 'DFGHTRJH';
$a = '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";

var_dump($a);*/
}
