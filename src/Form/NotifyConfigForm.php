<?php

namespace Drupal\content_notify\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\content_notify\ContentNotifyManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure content notification settings for this site.
 */
class NotifyConfigForm extends ConfigFormBase {

  /**
   * Module handler service object.
   *
   * @var \Drupal\content_notify\ContentNotifyManager
   */
  protected $contentNotifyManager;

  /**
   * Constructs a new GeneralConfForm object.
   */
  public function __construct(ContentNotifyManager $content_notify_manager) {
    $this->contentNotifyManager = $content_notify_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_notify.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_notify_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['content_notify.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('content_notify.settings');
    if ($this->contentNotifyManager->checkSchedulerExists()) {

      $form['notify_base_on_published_date'] = [
        '#title' => $this->t('Use published date for calculations'),
        '#description' => $this->t('Use published date (or workflow transition if set). If not set, uses creation date.'),
        '#type' => 'checkbox',
        '#default_value' => $config->get('notify_base_on_published_date'),
      ];

      $form['invalid'] = [
        '#title' => $this->t('Notify user of old content'),
        '#description' => $this->t('At creation of a node we automatically register a date in the future to remind the creator of the node to "check in" on the node to help the editor keep the site up to date.'),
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      $form['invalid']['notify_invalid_bundles'] = [
        '#title' => $this->t('Bundles to automatically send notification of old content about'),
        '#type' => 'checkboxes',
        '#options' => node_type_get_names(),
        '#default_value' => $config->get('notify_invalid_bundles'),
        '#description' => $this->t('On what bundles should we notify about old content.'),
      ];

      $form['invalid']['notify_invalid_time'] = [
        '#title' => $this->t('Days from publish date to set send mail about content validity.'),
        '#type' => 'number',
        '#field_suffix' => $this->t('Days'),
        '#default_value' => $config->get('notify_invalid_time'),
        '#description' => $this->t('How many days after publishing should a mail go out?.'),
      ];

      $form['invalid']['notify_invalid_time_2_offset'] = [
        '#title' => $this->t('Second notification, days after first notification'),
        '#type' => 'number',
        '#field_suffix' => $this->t('Days'),
        '#default_value' => $config->get('notify_invalid_time_2_offset'),
        '#description' => $this->t('Leave blank for no second notifcation. To set a second notification, for example, if initial notification is 150 days, and want another notification at 165 days, 15 days later, enter: 15'),
      ];

      $form['invalid']['email_settings'] = [
        '#type' => 'details',
        '#description' => $this->t('Mail will always go as digest email with all nodes per specific user'),
        '#title' => $this->t('Mail settings'),
        '#collapsed' => FALSE,
      ];
      $form['invalid']['email_settings']['notify_invalid_digest_duration'] = [
        '#title' => $this->t('Interval of digest email'),
        '#type' => 'select',
        '#options' => [
          '0' => $this->t('Immediately'),
          '7' => $this->t('Weekly'),
          '30' => $this->t('Monthly'),
        ],
        '#default_value' => $config->get('notify_invalid_digest_duration'),
        '#description' => $this->t('What should be interval of sending digest email.'),
      ];

      $form['invalid']['email_settings']['notify_invalid_receiver'] = [
        '#title' => $this->t('Receiver email address for notification old content'),
        '#type' => 'email',
        '#default_value' => $config->get('notify_invalid_receiver'),
        '#description' => $this->t('this email address will get notification. If you want content owner get email then leave this field empty'),
      ];

      $form['invalid']['email_settings']['notify_invalid_subject'] = [
        '#title' => $this->t('Subject'),
        '#type' => 'textfield',
        '#default_value' => $config->get('notify_invalid_subject'),
        '#description' => $this->t('What text should be sent as subject notification.'),
      ];

      $form['invalid']['email_settings']['notify_invalid_body'] = [
        '#title' => $this->t('Body'),
        '#type' => 'textarea',
        '#default_value' => $config->get('notify_invalid_body'),
        '#description' => $this->t('What text should be sent as notification. Tokens [content-notify:digest-nodes] is only available'),
      ];

      $form['notify'] = [
        '#title' => $this->t('Notifications about content about to be unpublished'),
        '#description' => $this->t('You need to set which bundles notices should be sent on. The bundles you choose need to have scheduler settings enabled.'),
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      $form['notify']['notify_unpublish_bundles'] = [
        '#title' => $this->t('Bundles to find unpublish dates in'),
        '#type' => 'checkboxes',
        '#options' => node_type_get_names(),
        '#default_value' => $config->get('notify_unpublish_bundles'),
      ];

      $form['notify']['notify_set_unpublish_time'] = [
        '#title' => $this->t('Days from creation date to auto expire node'),
        '#type' => 'number',
        '#field_suffix' => $this->t('Days'),
        '#default_value' => $config->get('notify_set_unpublish_time'),
        '#description' => $this->t('if the user does not actively set an unpublish date then you can set how many days from the creation of the node should auto expired? If user has set an unpublish date of the node then this value will not be used.'),
      ];

      $form['notify']['notify_unpublish_time'] = [
        '#title' => $this->t('Days before unpublishing to send notification'),
        '#type' => 'number',
        '#default_value' => $config->get('notify_unpublish_time'),
        '#field_suffix' => $this->t('Days'),
        '#description' => $this->t('How many days before unpublishing a notification e-mail be sent to the user?'),
      ];

      $form['notify']['email_settings'] = [
        '#type' => 'details',
        '#description' => $this->t('Mail will always go as digest email with all nodes per specific user'),
        '#title' => $this->t('Mail settings'),
        '#collapsed' => FALSE,
      ];

      $form['notify']['email_settings']['notify_unpublish_receiver'] = [
        '#title' => $this->t('Receiver email address for notification'),
        '#type' => 'email',
        '#default_value' => $config->get('notify_unpublish_receiver'),
        '#description' => $this->t('this email address will get notification. If you want owner of node to  get email then leave this field empty'),
      ];

      $form['notify']['email_settings']['notify_unpublish_subject'] = [
        '#title' => $this->t('Subject'),
        '#type' => 'textfield',
        '#default_value' => $config->get('notify_unpublish_subject'),
        '#description' => $this->t('What text should be sent as subject of notification.'),
      ];

      $form['notify']['email_settings']['notify_unpublish_body'] = [
        '#title' => $this->t('Body'),
        '#type' => 'textarea',
        '#default_value' => $config->get('notify_unpublish_body'),
        '#description' => $this->t('What text should be sent as notification. Tokens [content-notify:digest-nodes] is only available.'),
      ];
    }

    $form['unpublish_date_warning'] = [
      '#title' => $this->t('Unpublish date warning'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['unpublish_date_warning']['notify_include_unpublish_date_in_warning'] = [
      '#title' => $this->t('Include unpublish date in warning regarding old content'),
      '#description' => $this->t('Enable to include date and time.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('notify_include_unpublish_date_in_warning'),
    ];

    $form['unpublish_date_warning']['notify_unpublish_date_warning_text'] = [
      '#title' => $this->t('Warning text'),
      '#description' => $this->t('Will prefix date and time. For example: scheduled to be auto-archived'),
      '#type' => 'textfield',
      '#default_value' => $config->get('notify_unpublish_date_warning_text'),
    ];

    $form['unpublish_date_warning']['notify_date_format'] = [
      '#title' => $this->t('Date format'),
      '#description' => $this->t('For example: F j Y H:i T'),
      '#type' => 'textfield',
      '#default_value' => $config->get('notify_date_format'),
    ];

    $form['notify_ignore_translations'] = [
      '#title' => $this->t('Ignore translations'),
      '#description' => $this->t('Enable to ignore translations. (Recommended.)'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('notify_ignore_translations'),
    ];

    $form['notify_always_push_out_time'] = [
      '#title' => $this->t('Always extend out time'),
      '#description' => $this->t('When updating dates, update even if dates are already set.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('notify_always_push_out_time'),
    ];

    $module_workflows_enabled = \Drupal::moduleHandler()
      ->moduleExists('workflows');
    if ($module_workflows_enabled) {
      $form['workflow'] = [
        '#title' => $this->t('Workflow settings'),
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];

      $form['workflow']['notify_workflow_use_transition_criteria'] = [
        '#title' => $this->t('Use transition criteria'),
        '#description' => $this->t('Without this, criteria is just the node being saved is: Published. With this, the criteria is a transiton from any different state to the state specified below state.'),
        '#type' => 'checkbox',
        '#default_value' => $config->get('notify_workflow_use_transition_criteria'),
      ];

      $form['workflow']['notify_workflow_to_state'] = [
        '#title' => $this->t('To state'),
        '#type' => 'textfield',
        '#default_value' => $config->get('notify_workflow_to_state'),
        '#description' => $this->t('Machine name, for example: published'),
      ];
    }

    $form['debug_settings'] = [
      '#title' => $this->t('Debug settings'),
      '#type' => 'details',
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    ];

    $form['debug_settings']['notify_debug'] = [
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('Enable debug to override the default behavior. Do not deploy to production with this setting. Effects evaluation of times, but not setting of times.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('notify_debug'),
    ];

    $form['debug_settings']['notify_debug_last_cron_override'] = [
      '#title' => $this->t('Last cron run override'),
      '#description' => $this->t('Set when you want to pretend the last cron run was, for example -1days.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('notify_debug_last_cron_override'),
    ];

    $form['debug_settings']['notify_debug_current_time_override'] = [
      '#title' => $this->t('Current time override'),
      '#description' => $this->t('Set when you want to pretend to be another point in time, for example +155days.'),
      '#type' => 'textfield',
      '#default_value' => $config->get('notify_debug_current_time_override'),
    ];

    $form['array_filter'] = ['#type' => 'value', '#value' => TRUE];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $notify_unpublish_bundles = array_filter($form_state->getValue('notify_unpublish_bundles'));
    $notify_invalid_bundles = array_filter($form_state->getValue('notify_invalid_bundles'));

    sort($notify_unpublish_bundles);
    sort($notify_invalid_bundles);

    $values = $form_state->getValues();

    $this->config('content_notify.settings')
      ->set('notify_base_on_published_date', $values['notify_base_on_published_date'])
      ->set('notify_invalid_bundles', $notify_invalid_bundles)
      ->set('notify_invalid_digest_duration', $values['notify_invalid_digest_duration'])
      ->set('notify_invalid_receiver', $values['notify_invalid_receiver'])
      ->set('notify_invalid_time', $values['notify_invalid_time'])
      ->set('notify_invalid_time_2_offset', $values['notify_invalid_time_2_offset'])
      ->set('notify_invalid_subject', $values['notify_invalid_subject'])
      ->set('notify_invalid_body', $values['notify_invalid_body'])
      ->set('notify_include_unpublish_date_in_warning', $values['notify_include_unpublish_date_in_warning'])
      ->set('notify_date_format', $values['notify_date_format'])
      ->set('notify_unpublish_date_warning_text', $values['notify_unpublish_date_warning_text'])
      ->set('notify_ignore_translations', $values['notify_ignore_translations'])
      ->set('notify_always_push_out_time', $values['notify_always_push_out_time'])
      ->set('notify_workflow_use_transition_criteria', $values['notify_workflow_use_transition_criteria'])
      ->set('notify_workflow_to_state', $values['notify_workflow_to_state'])
      ->set('notify_debug', $values['notify_debug'])
      ->set('notify_debug_last_cron_override', $values['notify_debug_last_cron_override'])
      ->set('notify_debug_current_time_override', $values['notify_debug_current_time_override'])
      ->save();

    if ($this->contentNotifyManager->checkSchedulerExists()) {
      $this->config('content_notify.settings')
        ->set('notify_unpublish_bundles', $notify_unpublish_bundles)
        ->set('notify_unpublish_receiver', $values['notify_unpublish_receiver'])
        ->set('notify_unpublish_time', $values['notify_unpublish_time'])
        ->set('notify_unpublish_subject', $values['notify_unpublish_subject'])
        ->set('notify_unpublish_body', $values['notify_unpublish_body'])
        ->set('notify_set_unpublish_time', $values['notify_set_unpublish_time'])
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
