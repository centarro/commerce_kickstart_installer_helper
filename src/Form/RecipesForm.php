<?php

namespace Centarro\InstallerHelper\Form;

use Composer\InstalledVersions;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\RecipeKit\Installer\FormInterface as InstallerFormInterface;

final class RecipesForm extends FormBase implements InstallerFormInterface {

  /**
   * {@inheritdoc}
   */
  public static function toInstallTask(array $install_state): array {
    // Skip this form if optional recipes have already been chosen, or if the
    // profile doesn't define any optional recipe groups.
    if (array_key_exists('demo', $install_state['parameters']) || array_key_exists('recipes', $install_state['parameters']) || empty($install_state['profile_info']['recipes']['optional'])) {
      $run = INSTALL_TASK_SKIP;
    }
    return [
      'display_name' => t('Choose demo or add-ons'),
      'type' => 'form',
      'run' => $run ?? INSTALL_TASK_RUN_IF_REACHED,
      'function' => static::class,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'installer_recipes_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $install_state = NULL): array {
    assert(is_array($install_state));

    try {
      InstalledVersions::getInstallPath('drupal/commerce_kickstart_demo');
      $form['demo'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Install all features with sample content'),
        '#description' => $this->t('Great for seeing all that Drupal Commerce has to offer. Not recommended for a site you intend to take live'),
        '#return_value' => 'drupal/commerce_kickstart_demo',
      ];
    }
    catch (\Exception $e) {
      $form['demo']['#access'] = FALSE;
      $form['demo_info']['#markup'] = t('Add the Commerce Demo recipe to your codebase and reload this page if you want to install a complete demo store with sample content: <p><pre>composer require drupal/commerce_kickstart_demo</pre></p>');
    }

    $flavors = array_keys($install_state['profile_info']['recipes']['optional'] ?? []);
    $form['add_ons'] = [
      '#tree' => TRUE,
    ];
    foreach (array_combine($flavors, $flavors) as $flavor) {
      $form['add_ons'][$flavor] = [
        '#type' => 'checkbox',
        '#title' => $flavor,
        '#default_value' => $flavor,
        '#states' => [
          'disabled' => [
            ':input[name="demo"]' => ['checked' => TRUE],
          ],
          'checked' => [
            ':input[name="demo"]' => ['checked' => TRUE],
          ]
        ],
      ];
    }

    $form['actions'] = [
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
        '#op' => 'submit',
      ],
      '#type' => 'actions',
    ];
    $form['#title'] = '';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;
    $list = [];

    $pressed_button = $form_state->getTriggeringElement();

    // If demo was selected we
    if ($demo = $form_state->getValue('demo', [])) {
      $list[] = $demo;
    }

    // Only choose add-ons if the Next button was pressed, or if the form was
    // submitted programmatically (i.e., by `drush site:install`).
    if (($pressed_button && $pressed_button['#op'] === 'submit') || $form_state->isProgrammed()) {
      $flavors = $form_state->getValue('add_ons', []);
      $flavors = array_filter($flavors);
      foreach (array_keys($flavors) as $flavor) {
        $list = array_merge($list, $install_state['profile_info']['recipes']['optional'][$flavor]);
      }
    }
    // A NULL parameter will simply be encoded into the URL query string like
    // `?site_name=Foo&recipes`, which will satisfy the `array_key_exists()`
    // check in ::toInstallTask() when the query string is decoded.
    // @see \Drupal\Component\Utility\UrlHelper::buildQuery()
    $install_state['parameters']['recipes'] = $list ? array_unique($list) : NULL;
  }

}
