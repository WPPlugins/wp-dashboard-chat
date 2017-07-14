<?php
/*
Plugin Name: WP Dashboard Chat
Plugin URI: http://nicholasbosch.com/wp-dashboard-chat
Description: WP Dashboard Chat is a Twitter inspired widget, which displays on the admin dashboard. It allows for quick communication between users and admins.
Version: 1.0.3
Author: Nicholas Bosch
Author URI: http://nicholasbosch.com/
License: GPLv2 or Later

	Copyright 2011  Nicholas Bosch  (email : nick@boschdesign.com.au)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WPDashboardChat {

	private $table_name;
	private $current_user;
	private $ajax;
	private $action;
	private $options;
	private $defaults;
	private $rolls = array(
		'Administrator' => 'activate_plugins',
		'Editor'        => 'moderate_comments',
		'Author'        => 'edit_published_posts',
		'Contributor'   => 'edit_posts',
		'Subscriber'    => 'read'
	);
	
	function WPDashboardChat($ajax = false) {
		$this->__construct($ajax);
	}
	
	function __construct($ajax = false) {
		global $current_user, $wpdb;
		get_currentuserinfo();
		
		$this->table_name = $wpdb->prefix . "dashboard_chat";
		$this->current_user = $current_user;
		$this->ajax = $ajax;
		$this->action = $_REQUEST['fn'];
		
		$this->defaults = array(
			'hist_len' => 50,
			'title'    => 'Dashboard Chat'
		);
		
		$this->options = array(
			'db_version'         => '1.0.2',
			'install_db_version' => get_option( "wp_dashboard_chat_db_version" ),
			'hist_len'           => get_option( "wp_dashboard_chat_hist_len" ),
			'title'              => get_option( "wp_dashboard_chat_title" )
		);
		
		if ( $this->options['db_version'] != $this->options['install_db_version'] ) {
			$this->install();
		}
		
		if ( $this->ajax ) {
			$this->ajax();
		} else {
			$this->display();
		}
	}
	
	private function install() {
		if ( $this->options['db_version'] ) {
			update_option("wp_dashboard_chat_db_version", $this->options['db_version'], '', 'no');
		} else {
			add_option("wp_dashboard_chat_db_version", $this->options['db_version'], '', 'no');
		}
		
		if ( !$this->options['hist_len'] ) {
			add_option( "wp_dashboard_chat_hist_len", $this->defaults['hist_len'], '', 'no' );
		}
		
		if ( !$this->options['title'] ) {
			add_option( "wp_dashboard_chat_title", $this->defaults['title'], '', 'no' );
		}
		
		$sql = "CREATE TABLE " . $this->table_name . "(id bigint(20) NOT NULL AUTO_INCREMENT, user_id bigint(20) NOT NULL, date datetime NOT NULL, content text NOT NULL, PRIMARY KEY (id)) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   		dbDelta($sql);
	}
	
	private function ajax() {
		global $wpdb;
		if (wp_verify_nonce($_REQUEST['nonce'], 'message_nonce') ) {
			switch($this->action) {
				case 'add_message':
					$message = $this->addMessage($_REQUEST['message']);
					echo $wpdb->insert_id;
				break;
				case 'delete_message':
					$this->deleteMessage($_REQUEST['id']);
					echo '1';
				break;
				case 'get_message':
					$this->render_message($this->getMessageById($_REQUEST['id']));
				break;
				case 'refresh':
					foreach ($this->listMessages() as $message) {
						if ($message->id > $_REQUEST['id']) {
							$this->render_message($message);
						}
					}
				break;
			}
		} else {
			die('e');
		}
		die();
	}
	
	private function enqueue() {
		wp_register_script( 'wp-dashboard-chat', plugins_url( '/chat.js', __FILE__) );
    	wp_enqueue_script( 'wp-dashboard-chat' );
		wp_register_style( 'wp-dashboard-chat', plugins_url( '/chat.css', __FILE__) );
    	wp_enqueue_style( 'wp-dashboard-chat' );  
	}
	
	private function display() {
 		
 		$this->enqueue();
 		
		$nonce = wp_create_nonce('message_nonce');
		
		echo "<div id=\"chat_wrapper\"><table id=\"messages\">";
		
		foreach ($this->listMessages() as $message) {
			$this->render_message($message);
		}
		
		echo "</table></div>";
		
		echo '<form id="new_message" method="post" action="' . $_SERVER['PHP_SELF'] . '">';
    	echo '<textarea id="message" name="message"></textarea>';
    	echo '<input type="hidden" id="message_nonce" name="nonce" value="' . $nonce . '" />';
    	echo '<input type="submit" id="submit_message" name="submit_message" value="Post" class="button-primary" />';
    	echo '<input type="button" id="scroll" name="scroll" value="Scroll To Bottom" class="button" />';
    	echo '<input type="button" id="refresh" name="refresh" value="Refresh" class="button" />';
    	echo '</form>';
	}
	
	private function render_message($message) {
		$author = get_userdata($message->user_id);
		echo "<tr id=\"message_" . $message->id . "\" data-mid=\"" . $message->id . "\">";
		echo '<td class="avatar">' . get_avatar( $message->user_id , 32 ) . '</td>';
		echo '<td class="message">';
		echo nl2br(stripslashes($this->prettify($message->content)));
		echo '<span class="meta">' . $author->nickname . ' - ' . human_time_diff( strtotime($message->date) ) . ' ago</span>';
		echo '</td><td class="actions">';
		if ($message->user_id == $this->current_user->ID ) {
			echo '<a href="javascript:void(0)" class="del" data-mid="' . $message->id . '"></a>';
		} else {
			echo '<a href="javascript:void(0)" class="rep" data-mid="' . $message->id . '" username="' . $author->nickname . '"></a>';
		}
		echo '</td></tr>';
	}
	
	private function listMessages() {
		global $wpdb;
		return array_reverse($wpdb->get_results("SELECT * FROM " . $this->table_name . " ORDER BY id DESC LIMIT 50"));
	}
	
	private function addMessage($message) {
		global $wpdb;
		$message = $wpdb->prepare($message);
		$rows_affected = $wpdb->insert( $this->table_name, array( 
			'date' => date( 'Y-m-d H:i:s', time() ),
			'user_id' => $this->current_user->ID,
			'content' => $message
		));
		return $rows_affected;
	}
	
	private function updateMessage($message, $id) {
		global $wpdb;
		$message = $wpdb->prepare($message);
		$rows_affected = $wpdb->update( $this->table_name, array( 
			'date' => date( 'Y-m-d H:i:s', time() ),
			'user_id' => $this->current_user->ID,
			'content' => $message
		), array(
			'id' => $id
		));
	}
	
	private function deleteMessage($id) {
		global $wpdb;
		return $wpdb->query("DELETE FROM " . $this->table_name . " WHERE id = $id") ? true : false;
	}
	
	private function getMessageById($id) {
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM " . $this->table_name . " WHERE id = $id");
	}
	
	private function prettify($msg) {
		$msg = strip_tags($msg, '<u><s><q><em><code><cite><caption><b><strong><strike><i><ul><li><ol><p><a><blockquote><br><pre>');
		$msg = preg_replace("#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", "\\1<a href=\"\\2\" target=\"_blank\">\\2</a>", $msg);
		$msg = preg_replace("#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", "\\1<a href=\"http://\\2\" target=\"_blank\">\\2</a>", $msg);
		//$msg = preg_replace("/@(\w+)/", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>", $msg);
		//$msg = preg_replace("/#(\w+)/", "<a href=\"http://search.twitter.com/search?q=\\1\" target=\"_blank\">#\\1</a>", $msg);
		//$msg = preg_replace("/@(\w+)/", "<a href=\"http://twitter.com/\\1\" target=\"_blank\">@\\1</a>", $msg);
		$msg = preg_replace("/@(\w+)/", "<a href=\"#\">@\\1</a>", $msg);
		//$msg = preg_replace("/@(\w+)/", "<a href=\"http://www.vambient.com/author/\\1\" target=\"_blank\">@\\1</a>", $msg);
		//$msg = preg_replace("/#(\w+)/", "<a href=\"http://www.vambient.com/?s=\\1\" target=\"_blank\">#\\1</a>", $msg);
		return $msg;
	}
		
}

function wp_dashboard_chat() {
	$chat = new WPDashboardChat;
}

function ajax() {
	$chat = new WPDashboardChat(true);
}

function add_dashboard_widgets() {
	if (current_user_can( 'edit_posts' )) {
		wp_add_dashboard_widget('wp_dashboard_chat', 'Dashboard Chat', 'wp_dashboard_chat');
	}
}

add_action('wp_dashboard_setup', 'add_dashboard_widgets' );
add_action('wp_ajax_dashboard_chat', 'ajax');

?>