<?php

namespace Drupal\bos_metrolist\Plugin\views\style;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Annotation\ViewsStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Unformatted style plugin to render rows one after another with no
 * decorations.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "metro_list_drawers_style",
 *   title = @Translation("MetroList Drawers"),
 *   help = @Translation("Displays rows one after another in Drawers."),
 *   theme = "views_view_metrolist_drawers",
 *   display_types = {"normal"}
 * )
 */
class MetroListDrawersStyle extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;


  protected function defineOptions()
  {
    $options = parent::defineOptions(); // TODO: Change the autogenerated stub

    $options['show_email_button'] = ['default' => FALSE];
    $options['show_print_button'] = ['default' => FALSE];
    $options['show_all_units_button'] = ['default' => FALSE];

    $options['email_button_text'] = ['default' => ''];
    $options['print_button_text'] = ['default' => ''];
    $options['show_all_units_button_text'] = ['default' => ''];
    $options['hide_all_units_button_text'] = ['default' => ''];

    $options['remove_duplicates'] = ['default' => FALSE];


    return $options;
  }


  public function buildOptionsForm(&$form, FormStateInterface $form_state)
  {
    parent::buildOptionsForm($form, $form_state); // TODO: Change the autogenerated stub

    $form['show_email_button'] = [
      '#title' => $this->t('Show Email Button'),
      '#description' => $this->t('Enter email button text'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_email_button'],
      '#attributes' => [
        'id' => 'show-email-button',
      ],
    ];

    $form['email_button_text'] = [
      '#type' => 'textfield',
      '#size' => '20',
      '#description' => $this->t('Enter email button text'),
      '#default_value' => $this->options['email_button_text'] ?? 'Email',
      '#attributes' => [
        'id' => 'email-button-text',
      ],
      '#states' => [
        'visible' => [
          ':input[id="show-email-button"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['show_print_button'] = [
      '#title' => $this->t('Show Print Button'),
      '#description' => $this->t('Enter email button text'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_print_button'],
      '#attributes' => [
        'id' => 'show-print-button',
      ],
    ];

    $form['print_button_text'] = [
      '#type' => 'textfield',
      '#size' => '20',
      '#description' => $this->t('Enter print button text'),
      '#default_value' => $this->options['print_button_text'] ?? 'Print',
      '#attributes' => [
        'id' => 'print-button-text',
      ],
      '#states' => [
        'visible' => [
          ':input[id="show-print-button"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['show_all_units_button'] = [
      '#title' => $this->t('Show All Units Button'),
      '#description' => $this->t('Enter email button text'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_all_units_button'],
      '#attributes' => [
        'is' => 'show-all-units-button',
      ],
    ];

    $form['show_all_units_button_text'] = [
      '#type' => 'textfield',
      '#size' => '20',
      '#description' => $this->t('Enter show all units button text'),
      '#default_value' => $this->options['show_all_units_button_text'] ?? 'View All Units',
      '#attributes' => [
        'id' => 'show-all-units-button-text',
      ],
      '#states' => [
        'visible' => [
          ':input[id="show-all-units-button"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['hide_all_units_button_text'] = [
      '#type' => 'textfield',
      '#size' => '20',
      '#description' => $this->t('Enter hide all units button text'),
      '#default_value' => $this->options['hide_all_units_button_text'] ?? 'Hide More Units',
      '#attributes' => [
        'id' => 'hide-all-units-button-text',
      ],
      '#states' => [
        'visible' => [
          ':input[id="show-all-units-button"]' => ['checked' => TRUE],
        ],
      ],
    ];

  }

}
