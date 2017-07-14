(function($) {
	
	jQuery.fn.selectRange = function(start, end) {
    		return this.each(function() {
        		if (this.setSelectionRange) {
            		this.focus();
            		this.setSelectionRange(start, end);
        		} else if (this.createTextRange) {
            		var range = this.createTextRange();
            		range.collapse(true);
            		range.moveEnd('character', end);
            		range.moveStart('character', start);
            		range.select();
        		}
    		});
		};
	
	$(document).ready(function() {
		var $chat = $("#wp_dashboard_chat");
		var $wrapper = $("#chat_wrapper");
		var $messages = $("#messages");
		var $message = $("#new_message #message");
		var latest = parseInt($("#messages tr").last().data('mid'));
		var is_refreshing = false;
		
		function refresh() {
			is_refreshing = true;
			var post_vars = {
				'action' : 'dashboard_chat',
				'fn' : 'refresh',
				'id' : latest,
				'nonce' : $("#message_nonce").val()
			};
			$.post(ajaxurl, post_vars, function(l) {
					if (l != '' && l != '0' && l != '-1' ) {
						$messages.append(l);
						scrollWrapper();
						latest = parseInt($("#messages tr").last().data('mid'));
						is_refreshing = false;
					}
			});
		}
		
		function scrollWrapper(fn) {
			if (typeof fn == "undefined") fn = function() { };
			$wrapper.animate({ scrollTop: $wrapper.prop("scrollHeight") }, 1000, fn);
		}
		
		scrollWrapper(function() {
			refresh();
		});
		
		var ref = setInterval(function() {
			if ( !is_refreshing ) {
				refresh();
			}
		}, 5000);
		
		$("#new_message").submit(function(e) {
			e.preventDefault();
			var post_vars = {
				'action' : 'dashboard_chat',
				'fn' : 'add_message',
				'message' : $message.val(),
				'nonce' : $("#message_nonce").val()
			};
			$.post(ajaxurl, post_vars , function(data) {
				if (data != "e" || data != "") {
					$message.val('');
					refresh();
					//latest = parseInt($("#messages tr").last().data('mid'));
				} else {
					alert('An error has occured. Please try again.');
				}
			});
		});
		
		$("#scroll").on('click', function() {
			scrollWrapper();
		});
		
		$("#refresh").on('click', function() {
			refresh();
		});
		
		$messages.on('click', 'a.del', function(e) {
			e.preventDefault();
			var $mid = $(this).data('mid');
			var post_vars = {
				'action' : 'dashboard_chat',
				'fn' : 'delete_message',
				'id' : $mid,
				'nonce' : $("#message_nonce").val()
			};
			$("#message_" + $mid).fadeOut(400);
			$.post(ajaxurl, post_vars , function(data) {
				if (data != "1") {
					alert('An error has occured. Please try again.');
					$("#message_" + $mid).show();
				}
			});
		});
		
		$messages.on("click", "a.rep", function() {
			var username = $(this).attr('username');
			$('#new_message #message').val('@' + username + ' ').selectRange(username.length + 2 ,username.length + 2);
		});
		
	});
	
})(jQuery);