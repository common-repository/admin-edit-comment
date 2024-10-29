/*global localize */
jQuery(function ($) {
	var ajax_url = localize.ajax_url;

	// Insert comment.
	$(document).on('click', 'input[name="aec_submit"]', function () {
		insertComment();
	});

	// Delete comment.
	$(document).on('click', 'span.aec_delete', function () {
		commentDelete($(this).attr('comment_id'));
	});

	// Delete response message.
	$(document).on('click', '.aec-msg button', function () {
		$('#admin_edit_comment div.aec-msg').hide('fast', function () {
			$(this).remove();
		});
	});
	$('[name="aec_comment_text_area"]').focus(function () {
		$('#admin_edit_comment div.aec-msg').hide('fast', function () {
			$(this).remove();
		});
	});

	// Show and hide message.
	$(document).on('click', '#aec_checkbox_wrap input[type=checkbox]', function () {
		let aec_type_id = $(this).attr('id').replace('aec_checkbox_', '');
		if ($(this).prop("checked") === false) {
			$('#aec_comment_wrap .'+aec_type_id).hide();
		} else {
			$('#aec_comment_wrap .'+aec_type_id).show();
		}
	});

	/**
	 * Insert comment.
	 *
	 * @returns {boolean}
	 */
	function insertComment() {
		var $submit = $('input[name="aec_submit"]'),
			$comment = $("#aec_comment_wrap"),
			$text = $('[name="aec_comment_text_area"]'),
			$message = $('#admin_edit_comment div.aec-msg'),
			post_id = $('input[name="post_ID"]').val(),
			limit = $('input[name="aec_limit"]').val();

		$submit.prop("disabled", true);
		$message.remove();

		if ($text.val() === '') {
			notice(localize.no_empty_msg, 'error');
			$submit.prop("disabled", false);
			return false;
		}

		if (limit === 'exceeds') {
			notice(localize.comments_limit_msg, 'error');
			$submit.prop("disabled", false);
			return false;
		}

		$.ajax({
			type: 'POST',
			url: ajax_url,
			data: {
				action: 'aec_insert_comment',
				post_id: post_id,
				comment: $text.val()
			},
			dataType: 'json'
		}).done(function (res) {
			if (res.success) {
				$comment.empty();
				$comment.append(res.data.comments);
				$text.val('');
			} else {
				notice(res.data.message, 'error');
			}
			$submit.prop("disabled", false);
		}).fail(function () {
			notice(localize.update_failed_msg, 'error');
			$submit.prop("disabled", false);
		});
	}

	/**
	 * Delete comment.
	 *
	 * @param comment_id
	 */
	function commentDelete(comment_id) {
		var $message = $('#admin_edit_comment div.aec-msg'),
			post_id = $('input[name="post_ID"]').val(),
			$comment = $("#aec_comment_wrap");

		$('span.aec_delete').css('pointer-events', 'none');
		$message.remove();

		$.ajax({
			type: 'POST',
			url: ajax_url,
			data: {
				action: 'aec_delete_comment',
				post_id: post_id,
				comment_id: comment_id
			},
			dataType: 'json'
		}).done(function (res) {
			if (res.success) {
				$comment.empty();
				$comment.append(res.data.comments);
			} else {
				notice(res.data.message, 'error');
			}
		}).fail(function () {
			notice(localize.delete_failed_msg, 'error');
		});
	}

	/**
	 * Display response message.
	 *
	 * @param message
	 * @param add_class
	 */
	function notice(message, add_class) {
		var $element = $('#aec_comment_wrap');
		$element.before('<div class="aec-msg ' + add_class + '"><p>' + message + '</p><button type="button" class="notice-dismiss"></button></div>');
	}

	if ( window.wp && wp.data && wp.data.select && wp.data.select( 'core/editor' ) ) {
		wp.data.subscribe(function () {
			var isSavingPost = wp.data.select('core/editor').isSavingPost();
			var isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();

			if (isSavingPost && !isAutosavingPost) {
				var $text    = $('[name="aec_comment_text_area"]'),
					$message = $('#admin_edit_comment div.aec-msg'),
					post_id  = $('input[name="post_ID"]').val(),
					$comment = $("#aec_comment_wrap");

				$message.remove();

				$.ajax({
					type: 'POST',
					url: ajax_url,
					data: {
						action: 'aec_refresh_comment',
						post_id: post_id
					},
					dataType: 'json'
				}).done(function (res) {
					if (res.success) {
						$comment.empty();
						$comment.append(res.data.comments);
						$text.val('');
					} else {
						notice(res.data.message, 'error');
					}
				}).fail(function () {
					notice(localize.update_failed_msg, 'error');
				});
			}
		});
	}
});
