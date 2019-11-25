<?php

namespace Drupal\content_notify\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for extending content_notify dates.
 */
class NotifyExtendForm extends FormBase {

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a NotifyExtendForm object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   */
  public function __construct(EntityStorageInterface $node_storage, ConfigFactory $configFactory) {
    $this->nodeStorage = $node_storage;
    $this->configFactory = $configFactory;
    $this->config = $this->configFactory->get('content_notify.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'notify_extend';
  }

  /**
   * {@inheritdoc}
   *
   * @param int $node
   *   The nid.
   * @param string $langcode
   *   The langcode.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $this->node = $this->nodeStorage->load($node);
    $user = \Drupal::currentUser();

    $config = $this->config;
    $days_default = $config->get('notify_extend_days_default');

    $form['#access'] = $user->hasPermission('content notification of nodes');

    $form['extend_days'] = [
      '#title' => $this->t(''),
      '#type' => 'number',
      '#field_suffix' => $this->t('Days'),
      '#default_value' => $days_default,
      '#description' => $this->t('This will extend the current dates stored for notifications and/or unpublishing.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Extend'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $days_input = $form_state->getValue('extend_days');

    // @todo Improve logic.
    $days = $days_input;
    $days_info = explode('-', $days_input);
    // If not starting with a minus, put a plus.
    if (count($days_info === 1)) {
      $days = '+' . $days;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = \Drupal::entityTypeManager()->getStorage('node')->load($this->node->id());
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $entity = $storage->createRevision($entity);

    $entity->setRevisionCreationTime(time());
    $revision_log_message = "Extended notification dates $days_input days.";
    $entity->setRevisionLogMessage($revision_log_message);
    $entity->setRevisionUserId($user->id());

    if (!empty($entity->unpublish_on->value)) {
      $entity->set('unpublish_on', date("U", strtotime($days . " days", $entity->unpublish_on->value)));
    }
    if (!empty($entity->unpublish_on->value)) {
      $entity->set('notify_unpublish_on', date("U", strtotime($days . " days", $entity->notify_unpublish_on->value)));
    }
    if (!empty($entity->unpublish_on->value)) {
      $entity->set('notify_invalid_on', date("U", strtotime($days . " days", $entity->notify_invalid_on->value)));
    }

    $entity->save();

    $this->messenger()->addStatus($this->t("The notifications and auto-archive have been extended $days_input days."));
  }

}
