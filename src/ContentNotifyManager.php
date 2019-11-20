<?php

namespace Drupal\content_notify;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;

/**
 * Defines a Content Notify manager.
 */
class ContentNotifyManager {

  /**
   * Module handler service object.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;


  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;


  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Drupal\Core\State\State definition.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * Constructs a ContentNotifyManager object.
   */
  public function __construct(ModuleHandler $moduleHandler, EntityTypeManagerInterface $entity_type_manager, ConfigFactory $configFactory, TimeInterface $time = NULL, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager, LoggerInterface $logger, StateInterface $state) {
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->get('content_notify.settings');
    $this->time = $time;
    $this->languageManager = $language_manager;
    $this->mailManager = $mail_manager;
    $this->logger = $logger;
    $this->state = $state;

  }

  /**
   * Notify unpublishing of nodes.
   */
  public function notifyUnpublished() {

    $bundles = $this->getConfig('notify_unpublish_bundles');
    $action = 'unpublish';
    if (!empty($bundles)) {
      $last_run = $this->state->get('content_notify_unpublish_last_run', 0);
      $current_time = $this->time->getRequestTime();
      $results = $this->getQuery($bundles, $action, $last_run, $current_time);
      // Allow other modules to alter the list of nodes to be published.
      $this->moduleHandler->alter('content_notify_nid_list', $results, $action);
      $email_list = $this->processResult($results, $action);
      $this->processEmail($email_list, $action);
      $this->state->set('content_notify_unpublish_last_run', $current_time);
    }
  }

  /**
   * Notify old nodes of system.
   */
  public function notifyInvalid() {
    $offset = $this->getConfig('notify_invalid_time_2_offset');
    $bundles = $this->getConfig('notify_invalid_bundles');
    $action = 'invalid';

    if (!empty($bundles)) {
      $duration = $this->getConfig('notify_invalid_digest_duration');
      $last_cron_run = $this->state->get('content_notify_invalid_last_run', 0);
      $current_time = $this->time->getRequestTime();
      $interval_time = strtotime("+$duration  day", $last_cron_run);
      if ($interval_time - $current_time <= 0) {
        // Get results in the first notification window.
        $results1 = $this->getQuery($bundles, $action, $last_cron_run, $current_time);
        // Get results that would be in the second notification window.
        $results2 = $this->getQuery($bundles, $action, $last_cron_run, $current_time, $offset);
        // It is unlikely, but depending on the settings, or changes to the
        // settings, a piece of content might meet the criteria for both.
        // Avoid a user getting a duplicate notification.
        // Combine results, filtering out duplicates.
        $results = array_unique(array_merge($results1, $results2), SORT_REGULAR);

        // Allow other modules to alter the list of nodes to be published.
        $this->moduleHandler->alter('content_notify_nid_list', $results, $action);
        $email_list = $this->processResult($results, $action);
        $this->processEmail($email_list, $action);
        $this->state->set('content_notify_invalid_last_run', $current_time);
      }
    }
  }

  /**
   * Process the email to send to respective user email.
   *
   * @param array $email_list
   *   The array contains all receiver information with nodes.
   * @param string $action
   *   The action that needs to be checked. Can be 'unpublish' or 'invalid'.
   */
  public function processEmail(array $email_list, $action) {

    $params['subject'] = $this->getConfig('notify_' . $action . '_subject');
    $body = $this->getConfig('notify_' . $action . '_body');
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    foreach ($email_list as $receiver => $email) {
      $params['message'] = $this->bodyTokenReplace($body, $email['nodes']);
      $params['receiver'] = $receiver;

      // Check that other modules allow the action on this node.
      if ($this->isSend($params, $action)) {
        $result = $this->mailManager->mail('content_notify', $action, $receiver, $langcode, $params, NULL, TRUE);
        if ($result['result'] !== TRUE) {
          $this->logger->error('There was a problem sending notification:@action to email:@receiver', [
            '@ction' => $action,
            '@receiver' => $receiver,
          ]);
        }
        else {
          $this->logger->notice('Notification:@action has been send to email:@receiver and @body', [
            '@action' => $action,
            '@receiver' => $receiver,
            '@body' => $params['message'],
          ]);
        }
      }
    }
  }

  /**
   * Checks whether email has been send by other modules.
   *
   * This provides a way for other modules to send notification of content
   * for invalid and reminder of unpublish,
   * by implementing hook_content_notify_send_unpublish() or
   * hook_content_notify_send_invalid().
   *
   * @param array $params
   *   The array contains all mail send elements.
   * @param string $action
   *   The action that needs to be checked. Can be 'unpublish' or 'invalid'.
   *
   * @return bool
   *   TRUE if the action is allowed, FALSE if not.
   *
   * @see hook_content_notify_send_publish()
   * @see hook_content_notify_send_invalid()
   */
  public function isSend(array $params, $action) {
    // Default to TRUE means you will send through drupal mail.
    $result = TRUE;
    // Check that other modules send notification already.
    // Sometimes you send email through other way rather use drupal common mail.
    $hook = 'content_notify_send_' . $action;
    foreach ($this->moduleHandler->getImplementations($hook) as $module) {
      $function = $module . '_' . $hook;
      $result &= $function($params);
    }
    return $result;
  }

  /**
   * Replace the token for body field.
   *
   * @param string $body
   *   Contains all information which will be in body of email.
   * @param array $nodes
   *   Nodes information of how it will be in body of email.
   */
  protected function bodyTokenReplace($body, array $nodes) {
    $newline = '
    <br>
    ';
    $digest_nodes = $newline . implode($nodes, $newline);
    $body = str_replace('[content-notify:digest-nodes]', $digest_nodes, $body);
    return $body;

  }

  /**
   * Process the result which receive from query about nids.
   *
   * This provides a way for other modules to alter
   * the nodes information which
   * attached to body of the email for invalid
   * and reminder of unpublish,
   * by implementing hook_content_notify_digest_nodes_alter()
   *
   * @param array $results
   *   Array of objects. Each object is information about the result with
   *   properties: nid, vid, langcode, ...
   * @param string $action
   *   The action that needs to be checked. Can be 'unpublish' or 'invalid'.
   *
   * @return array
   *   $email_list contains information of receiver
   *   with nodes links attached in email body.
   *
   * @see hook_content_notify_digest_nodes_alter($link,$action)
   */
  public function processResult(array $results, $action) {
    $include_date = $this->getConfig('include_unpublish_date_in_warning');

    $date_format = $this->getConfig('date_format');
    if (empty($date_format)) {
      $date_format = 'F j Y H:i T';
    }

    $warning_text = $this->getConfig('unpublish_date_warning_text');
    if (empty($warning_text)) {
      $warning_text = 'scheduled to be auto-archived';
    }

    $bundles_to_unpublish = $this->getConfig('notify_unpublish_bundles');

    $email_list = [];

    foreach ($results as $key => $result) {
      $node = $this->entityTypeManager->getStorage('node')
        ->loadRevision($result->vid);

      $translation = $node->getTranslation($result->langcode);
      $receiver = $this->getEmail($translation, $action);

      $language_manager = \Drupal::languageManager();
      $language = $language_manager->getLanguage($result->langcode);

      $options = ['absolute' => TRUE];
      $options += ['language' => $language];
      $node_url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options)
        ->toString();

      $link = ' * ' . $translation->getTitle() . '<br> - ' . $node_url;

      if ($include_date
        && in_array($node->bundle(), $bundles_to_unpublish)
        && !empty($result->notify_unpublish_on)
      ) {
        $eastern_time_zone = new \DateTimeZone('America/New_York');
        $date_time = new \Datetime();
        $date_time->setTimestamp($result->notify_unpublish_on);
        $date_time->setTimezone($eastern_time_zone);
        $unpublish_date_string = $date_time->format($date_format);
        $link .= "<br> - ($warning_text $unpublish_date_string)";
      }

      $link .= '<p>';

      // Allow other modules to alter link
      // which will be replace digest-title token.
      $this->moduleHandler->alter('content_notify_digest_nodes', $link, $node);
      $email_list[$receiver]['nodes'][$key] = $link;
    }
    return $email_list;
  }

  /**
   * Get the nids to process for notification email.
   *
   * @param array $bundles
   *   Node types to handle.
   * @param string $action
   *   The action that needs to be checked. Can be 'unpublish' or 'invalid'.
   * @param int $last_cron_run
   *   When last execute the query from state variable.
   * @param int $current_time
   *   Current time of the system.
   * @param int $offset
   *   (optional) Number of days for another notification window.
   *
   * @return array
   *   Array of objects. Each object is information about the result with
   *   properties: nid, vid, langcode, ...
   */
  public function getQuery(array $bundles, $action, $last_cron_run, $current_time, $offset = 0) {

    /** @var \Drupal\content_notify\ContentNotifyManager $content_notify_manager */
    $content_notify_manager = \Drupal::service('content_notify.manager');

    $debug = $content_notify_manager->getConfig('debug');

    if ($debug) {
      $debug_last_cron_override = $content_notify_manager->getConfig('debug_last_cron_override');
      $last_cron_run = strtotime($debug_last_cron_override);
      $debug_current_time_override = $content_notify_manager->getConfig('debug_current_time_override');
      $current_time = strtotime($debug_current_time_override);
    }

    // 60 sec * 60 minutes * 24 hours to get seconds in a day.
    $offset_unix_time = $offset * 86400;

    $database = \Drupal::database();
    $query = $database->select('node_field_data', 'n')
      ->condition('n.' . 'notify_' . $action . '_on', $current_time - $offset_unix_time, '<=')
      ->condition('n.' . 'notify_' . $action . '_on', $last_cron_run - $offset_unix_time, '>')
      ->condition('n.' . 'type', $bundles, 'IN')
      ->condition('n.' . 'status', 1)
      ->fields('n', ['nid', 'vid', 'langcode', 'notify_unpublish_on']);

    /** @var \Drupal\content_notify\ContentNotifyManager $content_notify_manager */
    $content_notify_manager = \Drupal::service('content_notify.manager');

    $ignore_translations = $content_notify_manager->getConfig('ignore_translations');

    if ($ignore_translations) {
      $query->condition('n.' . 'default_langcode', 1);
    }

    $results = $query->execute()->fetchAll();

    return $results;
  }

  /**
   * Rule of finding email receiver of notification email.
   *
   * This provides a way for other modules to alter the receiver email
   * for invalid or unpublish notification,
   * by implementing hook_content_notify_email_receiver_alter()
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node object to get receiver.
   * @param string $action
   *   The action that needs to be checked. Can be 'unpublish' or 'invalid'.
   */
  public function getEmail(NodeInterface $node, $action) {

    $receiver = $this->getConfig('notify_' . $action . '_receiver');
    if (!empty($receiver)) {
      $email = $receiver;
    }
    else {
      $email = $node->getOwner()->mail->value;
    }

    // Allow other modules to alter the email receiver.
    $this->moduleHandler->alter('content_notify_email_receiver', $email, $node, $action);
    return $email;
  }

  /**
   * Check whether scheduler module is exists in the system or not.
   *
   * @return bool
   *   True if scheduler module exists otherwise False.
   */
  public function checkSchedulerExists() {
    return $this->moduleHandler->moduleExists('scheduler');
  }

  /**
   * Check whether node type is checked for the respective action.
   *
   * @param string $action
   *   The action being performed, either "unpublish" or "invalid".
   * @param string $node_type
   *   Node type machine name.
   *
   * @return bool
   *   True if node type checked otherwise False.
   */
  public function checkNodeType($action, $node_type) {
    return in_array($node_type, $this->config->get('notify_' . $action . '_bundles'));
  }

  /**
   * Get the value of configuration variable.
   *
   * @param string $config_name
   *   Config variable name.
   *
   * @return value
   *   Value of the variable.
   */
  public function getConfig($config_name) {
    return $this->config->get($config_name);
  }

}
