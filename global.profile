<?php

/**
 * @file
 * Enables modules and site configuration for the Global profile.
 */

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Implements hook_node_presave().
 */
function global_node_presave(NodeInterface $node) {
  // Ensure only one homepage node is published.
  if ($node->bundle() === 'homepage'
    && $node->isPublished()
    && is_null($check_for_homepages = &drupal_static(__FUNCTION__))) {

    /** @var \Drupal\node\NodeInterface $original_entity */
    $original_entity = $node->original ?? NULL;
    // If the $original_entity exists, that means this presave hook is being
    // invoked because we are updating the existing entity. If it was already,
    // published, we don't need to check for existing homepages to unpublish.
    $check_for_homepages = $original_entity && $original_entity->isPublished() ? FALSE : TRUE;
    if ($check_for_homepages) {
      $entity_type = $node->getEntityType();
      /** @var \Drupal\node\NodeStorageInterface $node_storage */
      $node_storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
      $query = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $node->bundle())
        ->condition('status', NodeInterface::PUBLISHED);
      // If this $node has an ID associated with it, exclude it from the query
      // so that it won't be unpublished.
      if ($node->id()) {
        $query->condition($entity_type->getKey('id'), $node->id(), '!=');
      }
      $nids = $query->execute();

      /** @var \Drupal\node\NodeInterface[] $nodes */
      $nodes = $nids ? $node_storage->loadMultiple($nids) : [];
      foreach ($nodes as $homepage_node) {
        $homepage_node->setUnpublished()->save();
      }
    }
  }
}

/**
 * Implements hook_entity_bundle_field_info_alter().
 */
function global_entity_bundle_field_info_alter(&$fields, EntityTypeInterface $entity_type, $bundle) {
  if ($entity_type->id() === 'paragraph') {
    if ($bundle === 'card_group_button_cards') {
      // Check for a minimum number of required links.
      if (isset($fields['field_stylized_button_links'])) {
        $options = ['minimum' => 3];
        $fields['field_stylized_button_links']->addConstraint('LinkLimits', $options);
      }
    }
    if ($bundle === 'stats' && isset($fields['field_components'])) {
      // Check for the required number of stats items.
      $fields['field_components']->addConstraint('ParagraphLimits', [
        'types' => [
          'stats_item' => ['min' => 4, 'max' => 12],
        ],
      ]);
    }
    if ($bundle === 'group_links' && isset($fields['field_components'])) {
      // Check for the required group link items.
      $fields['field_components']->addConstraint('ParagraphLimits', [
        'types' => [
          'group_link' => ['min' => 3, 'max' => 3],
        ],
      ]);
    }
    if ($bundle === 'card_group_quick_nav_cards' && isset($fields['field_components'])) {
      // Check that the quick nav card items are multiples of three.
      $fields['field_components']->addConstraint('ParagraphLimits', [
        'types' => [
          'quick_nav_card' => ['multiple' => 3],
        ],
      ]);
    }
    if ($bundle === 'card_group_image_cards' && isset($fields['field_components'])) {
      // Check for the required number of image card items.
      $fields['field_components']->addConstraint('ParagraphLimits', [
        'types' => [
          'image_card' => ['min' => 2, 'max' => 15],
        ],
      ]);
    }
  }
}

/**
 * Implements hook_field_widget_complete_linkit_form_alter().
 */
function global_field_widget_complete_linkit_form_alter(&$field_widget_complete_form, FormStateInterface $form_state, $context) {
  $items = $context['items'] ?? NULL;
  if ($items instanceof FieldItemListInterface && $items->getName() === 'field_stylized_button_links') {
    for ($i = 0; isset($field_widget_complete_form['widget'][$i]['uri']); $i++) {
      $field_widget_complete_form['widget'][$i]['title']['#title'] = t('Card Title');
      $field_widget_complete_form['widget'][$i]['uri']['#title'] = t('Card Destination');
    }
  }
}

/**
 * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
 */
function global_field_widget_single_element_paragraphs_form_alter(array &$element, FormStateInterface $form_state, array $context) {
  $form_id = $form_state->getBuildInfo()['form_id'];

  // For the quick nav card group field, update the card group quick nav cards
  // component.
  if (
    $element['#paragraph_type'] === 'card_group_quick_nav_cards'
    && $context['items']->getFieldDefinition()->getName() === 'field_card_group_quick_nav_cards'
  ) {
    // Update the component description to allow only three quick nav cards.
    $element['subform']['field_components']['widget']['#description'] = FieldFilteredMarkup::create(t('Add three quick nav cards.'));
    // Disable the add button once the limit is reached.
    if (count(array_filter(array_keys($element['subform']['field_components']['widget']), 'is_numeric')) >= 3) {
      $element['subform']['field_components']['widget']['add_more']['add_more_button_quick_nav_card']['#access'] = FALSE;
    }
  }

  // For process landing page forms, alter Text component fields.
  $valid_forms = ['node_process_landing_form', 'node_process_landing_edit_form'];
  if ($element['#paragraph_type'] === 'text' && in_array($form_id, $valid_forms, TRUE)) {
    if (isset($element['subform']['field_title'])) {
      $element['subform']['field_title']['widget'][0]['value']['#required'] = TRUE;
      $element['subform']['field_title']['widget'][0]['value']['#description'] = FieldFilteredMarkup::create(t('Provide the step heading.'));
    }
    if (isset($element['subform']['body'])) {
      $element['subform']['body']['widget'][0]['#required'] = TRUE;
    }
  }
}

/**
 * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
 */
function global_field_widget_single_element_text_textfield_form_alter(array &$element, FormStateInterface $form_state, array $context) {
  _global_default_allowed_formats($element);
}

/**
 * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
 */
function global_field_widget_single_element_text_textarea_form_alter(array &$element, FormStateInterface $form_state, array $context) {
  _global_default_allowed_formats($element);
}

/**
 * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
 */
function global_field_widget_single_element_text_textarea_with_summary_form_alter(array &$element, FormStateInterface $form_state, array $context) {
  _global_default_allowed_formats($element);
}

/**
 * Set default allowed text formats where none are specified.
 *
 * @param array $element
 *   The field widget form element as constructed by
 *   \Drupal\Core\Field\WidgetBaseInterface::form().
 */
function _global_default_allowed_formats(array &$element) {
  $allowed_formats = $element['#allowed_formats'] ?? [];
  if (empty($allowed_formats)) {
    $element['#allowed_formats'] = [
      'basic_html',
      'restricted_html',
      'full_html',
    ];
  }
}

/**
 * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
 */
function global_field_widget_single_element_string_textfield_form_alter(array &$element, FormStateInterface $form_state, array $context) {
  /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
  $field_definition = $context['items']->getFieldDefinition();
  $field_name = $field_definition->getName();

  // Add a placeholder to the Phone Number field to exemplify the format.
  // Attach the masking library to aid in formatting on the frontend.
  if ($field_name === 'field_phone_number') {
    $element['value']['#attributes']['placeholder'] = t('e.g. (555) 555-5555');
    $element['value']['#attributes']['class'][] = 'psu-global-phone-number';
    $element['value']['#attached']['library'][] = 'global/mask';
    $element['value']['#element_validate'][] = '_global_phone_number_validation';
  }
}

/**
 * Element validation callback to validate that Phone Number field.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
function _global_phone_number_validation(array &$element, FormStateInterface $form_state, array &$complete_form) {
  if (!empty($element['#value'])) {
    // Remove everything that isn't a digit.
    $value = preg_replace('/\D/', '', $element['#value']);
    // Ensure there are exactly 10 digits; if not, set an error.
    if (strlen($value) !== 10) {
      return $form_state->setError($element, t('%title must match the format: (555) 555-5555', [
        '%title' => $element['#title'],
      ]));
    }
    // Format the phone number before writing to the DB.
    $value = sprintf('(%d) %d-%d', substr($value, 0, 3), substr($value, 3, 3), substr($value, -4));
    $form_state->setValueForElement($element, $value);
  }
}
