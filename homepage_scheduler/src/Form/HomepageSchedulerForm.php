<?php

namespace Drupal\homepage_scheduler\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Extends FormBase with the HomepageSchedulerForm options.
 */
class HomepageSchedulerForm extends FormBase {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Provides an interface for an entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Provides an interface for entity type managers.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * ReportWorkerBase constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service the instance should use.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Provides an interface for an entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Provides an interface for entity type managers.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(StateInterface $state, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->state = $state;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('state'),
          $container->get('entity_field.manager'),
          $container->get('entity_type.manager'),
          $container->get('datetime.time')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'homepage_scheduler_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Load previously saved config.
    $data = $this->state->get('homepage_scheduler.settings');

    // Build form.
    $form['scheduling_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable homepage scheduling'),
      '#default_value' => $data['enabled'] ?? 0,
    ];
    $form['status'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Takeover status: @status', [
        '@status' => $this->state->get('homepage_scheduler.status', 'unscheduled'),
      ]),
      '#prefix' => '<h4>',
      '#suffix' => '</h4>',
    ];
    $form['takeover'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Homepage Takeover Scheduler'),
      '#states' => [
        'disabled' => [
          [
            [':input[name="scheduling_enabled"]' => ['checked' => FALSE]],
          ],
        ],
      ],
    ];
    $form['takeover']['start_takeover'] = [
      '#type' => 'datetime',
      '#description' => $this->t('The date and time at which the takeover will be in effect.'),
      '#default_value' => $data['start'] ?? '',
      '#title' => $this->t('Start takeover on'),
    ];

    $form['takeover']['stop_takeover'] = [
      '#type' => 'datetime',
      '#description' => $this->t('The date and time at which the takeover will end.'),
      '#default_value' => $data['end'] ?? '',
      '#title' => $this->t('Stop takeover on'),
    ];
    $homepage_options = $this->getHomepageOptions();
    $form['takeover']['takeover_homepage'] = [
      '#type' => 'select',
      '#description' => $this->t('The published homepage node to be selected as the takeover homepage.'),
      '#title' => $this->t('Takeover Homepage'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => $homepage_options,
      '#default_value' => $data['takeover_nid'] ?? '',
    ];
    $form['default_homepage'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default Homepage'),
      '#states' => [
        'disabled' => [
          [
            [':input[name="scheduling_enabled"]' => ['checked' => FALSE]],
          ],
        ],
      ],
    ];
    $form['default_homepage']['default_homepage'] = [
      '#type' => 'select',
      '#description' => $this->t('The published homepage node to be used when the takeover ends.'),
      '#title' => $this->t('Default Homepage'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => $homepage_options,
      '#default_value' => $data['default_nid'] ?? '',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    if (!$form_state->getValue('scheduling_enabled')) {
      // If scheduling is disabled, no further validation is needed.
      return;
    }

    if ($form_state->getValue('start_takeover') === NULL) {
      $form_state->setErrorByName('start_takeover', $this->t('Please select a date for the beginning of the takeover.'));
    }

    if ($form_state->getValue('stop_takeover') === NULL) {
      $form_state->setErrorByName('stop_takeover', $this->t('Please select a date for the end of the takeover.'));
    }

    if ($form_state->getValue('takeover_homepage') === NULL) {
      $form_state->setErrorByName('takeover_homepage', $this->t('Please select a Homepage takeover node.'));
    }

    if ($form_state->getValue('start_takeover') !== NULL && $form_state->getValue('stop_takeover') !== NULL) {
      if ($form_state->getValue('start_takeover')->getTimestamp() >= $form_state->getValue('stop_takeover')->getTimestamp()) {
        $form_state->setErrorByName('stop_takeover', $this->t('The date for stopping the takeover should be after the start of the takeover.'));
      }
      if ($this->time->getRequestTime() >= $form_state->getValue('stop_takeover')->getTimestamp()) {
        $form_state->setErrorByName('stop_takeover', $this->t('The date for stopping the takeover should be in the future.'));
      }
    }

    if ($form_state->getValue('takeover_homepage') === $form_state->getValue('default_homepage')) {
      $form_state->setErrorByName('takeover_homepage', $this->t('The takeover homepage and the default homepage should be different.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Set values in form state.
    $data['enabled'] = $form_state->getValue('scheduling_enabled');
    $data['start'] = $form_state->getValue('start_takeover');
    $data['end'] = $form_state->getValue('stop_takeover');
    $data['takeover_nid'] = $form_state->getValue('takeover_homepage');
    $data['default_nid'] = $form_state->getValue('default_homepage');

    // Save form state.
    if (isset($data)) {
      $this->state->set('homepage_scheduler.settings', $data);
    }

    // Set the takeover status, which is used for triggering cron.  Set
    // status to pending unless scheduling is disabled.
    $this->state->set('homepage_scheduler.status', 'pending');
    if (!$form_state->getValue('scheduling_enabled')) {
      $this->state->set('homepage_scheduler.status', 'disabled');
    }

    // Display success message.
    $this->messenger()->addStatus($this->t('Configurations successfully saved.'));
  }

  /**
   * Get list of published homepage nodes.
   *
   * @return array
   *   Array of published homepage options, keyed by nid.
   */
  protected function getHomepageOptions() {
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'home_page')
      ->condition('status', 1)
      ->sort('title', 'ASC')
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $options = [];
    foreach ($nodes as $nid => $node) {
      $options[$nid] = $node->label() . " ($nid )";
    }
    return $options;
  }

}
