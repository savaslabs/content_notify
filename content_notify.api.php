<?php

/**
 * @file
 * API documentation for the content notify module.
 */

/**
 * @addtogroup hooks
 * @{
 */


/**
 * Hook function to manipulate the list of nodes being processed.
 *
 * This hook allows modules to add or remove node ids from the list being
 * processed in the current cron run. It is invoked during cron runs only.
 *
 * @param array $nids
 *   An array of node ids being processed.
 * @param string $action
 *   The action being done to the node - 'invalid' or 'unpublish'.
 *
 * @return array
 *   The full array of node ids to process, adjusted as required.
 */
function hook_content_notify_nid_list_alter(array &$nids, $action) {
  // Do some processing to add or remove node ids.
}

/**
 * Hook function to check whether email has been send from other modules
 * for unpublish notification.
 *
 * This hook gives modules the ability to prevent to use drupal mail
 * for notification rather you can implement you own function to send
 * notification.
 *
 * @param array $params
 *  Contains mail elements which needs to used for notification.
 *
 * @return bool
 *   TRUE if the drupal mail should be used, FALSE if mail already send
 * and you want to prevent drupal mail system.
 */
function hook_content_notify_send_unpublish($params) {
  return TRUE;
}

/**
 * Hook function to check whether email has been send from other modules
 * for invalid notification.
 *
 * This hook gives modules the ability to prevent to use drupal mail
 * for notification rather you can implement you own function to send
 * notification.
 *
 * @param array $params
 *  Contains mail elements which needs to used for notification.
 *
 * @return bool
 *   TRUE if the drupal mail should be used, FALSE if mail is already send
 * and you want to prevent drupal mail system.
 */
function hook_content_notify_send_invalid($params) {
  return TRUE;
}

/**
 * Hook function to alter the receiver email address.
 *
 * @param email $email
 *   receiver email address.
 * @param \Drupal\node\NodeInterface $node
 *   The node that is about to be processed.
 * @param string $action
 *   The action being done to the node - 'invalid' or 'unpublish'.
 */

function hook_content_notify_email_receiver_alter(&$email, $node, $action){
}

/**
 * Hook function to alter the link for per node in body of email.
 *
 * This hook allows modules to modify the output of each node
 * how it is going to attached in mail body.
 *
 * @param string $link
 *   basic link output.
 * @param \Drupal\node\NodeInterface $node
 *   The node that is about to be processed.
 *
 */
function hook_content_notify_digest_nodes_alter(&$link, NodeInterface $node) {
  // Do some processing with link.
}


/**
 * @} End of "addtogroup hooks".
 */
