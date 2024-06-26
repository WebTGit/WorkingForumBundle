jQuery(document).ready(() => {
    /**
     * A modo/admin move the thread
     */
    jQuery('#wf_move_thread_button').click(() => {
        if (!jQuery('#wf_move_thread_button').hasClass('confirm')) {
            jQuery('#move_thread_forum').show();
            jQuery('#wf_move_thread_button').html(storeJs.trans['forum.confirm_move_thread']);
            jQuery('#wf_move_thread_button').addClass('confirm');
        }
        else {
            const target = jQuery('#move_thread_forum').val();

            if (!target) {
                alert('Error: subforum id is empty');
                return false;
            }

            jQuery.ajax({
                type: 'POST',
                url: storeJs.routes.workingforum_move_thread,
                crossDomain: false,
                data: `threadId=${storeJs.threadId}&target=${target}`,
                dataType: 'json',
                async: false,
                success: (res) => {
                    if (res.res === 'true') {
                        alert(storeJs.trans['forum.move_thread_success']);
                        jQuery('#wf_move_thread_button').addClass('wf_button-grey').html(`${storeJs.trans['forum.thread_was_moved_to']} ${res.targetLabel}`);
                        jQuery('#move_thread_forum').hide();
                    }
                    else {
                        alert('An error occured');
                        return false;
                    }
                }
            });
        }
    });

    /**
     * Quote a message into the post editor
     * @param {int} postId
     */
    quote = (postId) => {
        jQuery('.wf_textarea_post_disabled').val(`${jQuery('.wf_textarea_post_disabled').val()}[quote=${postId}]\n`);
        jQuery('.wf_textarea_post_disabled').focus();
    }

    /**
     * Report a post
     * @param {string} url
     */
    report = (url) => {
        if (!confirm(storeJs.trans['forum.confirm_report'])) {
            return false;
        }
        jQuery.ajax({
            type: 'GET',
            url: url,
            crossDomain: false,
            dataType: 'json',
            async: false,
            success: (result) => {
                if (result === 'true') {
                    alert(storeJs.trans['forum.thanks_reporting']);
                }
                else {
                    alert(storeJs.trans['message.error.something_wrong']);
                }
            }
        });
    }

    /**
     * Moderate (censor content) of a post (modo/admin)
     * @param {int} id
     */
    moderate = (id) => {
        const reason = prompt(storeJs.trans['admin.report.why']);
        if (reason != null && reason.trim() != '') {
            jQuery.ajax({
                type: 'POST',
                url: storeJs.routes.workingforum_admin_report_action_moderate,
                crossDomain: false,
                data: `reason=${reason}&postId=${id}`,
                dataType: 'json',
                async: false,
                success: (result) => {
                    if (result === 'ok') {
                        const msg = `<p class="wf_moderate">${storeJs.trans['forum.post_moderated']} ${reason}</p>`;
                        jQuery('#wf_post\\[' + id+'\\] .wf_post_content').html(msg);
                    }
                }
            });
        } else if (reason != null) {
            alert(storeJs.trans['admin.report.invalid_reason']);
            return;
        }
    }

    /**
     * Positive vote for a post
     * @param {int} id
     * @param {HTMLObjectElement} element
     */
    voteUp = (id, element) => {
        jQuery.ajax({
            type: 'POST',
            url: storeJs.routes.workingforum_vote_up,
            crossDomain: false,
            data: `postId=${id}`,
            dataType: 'json',
            async: false,
            success: (content) => {
                if (content.res === 'true') {
                    const img = jQuery(element).html();
                    jQuery(element).remove();
                    jQuery(`#voteUpLabel_${id}`).html(`${img} + ${content.voteUp}`);
                }
                else {
                    alert(`Sorry an error occured : ${content.errMsg}`);
                }
            }
        });
    }

    /**
     * Unfold the block with enclosed file
     * @param {HTMLObjectElement} arrow
     * @param {int} id
     */

    showEnclosed =  (arrow, id) => {
        jQuery(`#wf_enclosed_files_${id}`).slideDown();
        jQuery(arrow).remove();
    }

    /**
     * Subscribe on new message
     */
    addSubscription = () => {
        jQuery.ajax({
            type: 'GET',
            url: storeJs.routes.workingforum_add_subscription,
            crossDomain: false,
            dataType: 'json',
            async: false,
            success: () => {
                jQuery("#wf_add_subscription").html('<i class="flaticon-cancel"></i>'+storeJs.trans["forum.cancel_subscription"]).addClass("btn-danger").removeClass("btn-primary").attr("onclick", "cancelSubscription(); return false;");
                jQuery("#wf_subscription_status").html('<i class="flaticon-alert text-white"></i>&nbsp;'+storeJs.trans["forum.status_subscribed"]).addClass("label-success").removeClass("label-danger");
            },
            error: () => {
                alert(storeJs.trans['message.generic_error']);
            }
        });
    }

    /**
     * Cancel subscription on new message
     */
    cancelSubscription = () => {
        jQuery.ajax({
            type: 'GET',
            url: storeJs.routes.workingforum_cancel_subscription,
            crossDomain: false,
            dataType: 'json',
            async: false,
            success: () => {
                jQuery("#wf_add_subscription").html('<i class="flaticon-email"></i>'+storeJs.trans["forum.add_subscription"]).addClass("btn-primary").removeClass("btn-danger").attr("onclick", "addSubscription(); return false;");
                jQuery("#wf_subscription_status").html('<i class="flaticon-alert-off text-white"></i>&nbsp;'+storeJs.trans["forum.status_not_subscribed"]).addClass("label-danger").removeClass("label-success");
            },
            error: () => {
                alert(storeJs.trans['message.generic_error']);
            }
        });
    }
});
