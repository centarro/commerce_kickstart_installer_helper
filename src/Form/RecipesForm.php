<?php

declare(strict_types=1);

namespace Centarro\InstallerHelper\Form;

use Composer\InstalledVersions;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\RecipeKit\Installer\Form\RecipeSelectionFormBase;
use Drupal\RecipeKit\Installer\FormInterface as InstallerFormInterface;

final class RecipesForm extends RecipeSelectionFormBase implements InstallerFormInterface {

  /**
   * {@inheritdoc}
   */
  public static function toInstallTask(array $install_state): array {
    // Skip this form if optional recipes have already been chosen.
    if (array_key_exists('recipes', $install_state['parameters'])) {
      $install_state['parameters']['add_ons'] = INSTALL_TASK_SKIP;
    }
    return [
      'display_name' => t('Choose demo or add-ons'),
      'type' => 'form',
      'run' => $install_state['parameters']['add_ons'] ?? INSTALL_TASK_RUN_IF_REACHED,
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
    // Rather than using composer, check recipes folder as unpacked recipes
    // won't be available via composer.
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

    $form = parent::buildForm($form, $form_state);
    $form['add_ons']['#value_callback'] = self::class . '::valueCallback';

    // We display this form even when no optional recipes were defined, thus
    // we must ensure the option list is set to empty array.
    if (!isset($form['add_ons']['#options'])) {
      $form['add_ons']['#options'] = [];
    }

    foreach ($this->getChoices() as $key => $choice) {
      $form['add_ons'][$key]['#states'] = [
        'disabled' => [
          ':input[name="demo"]' => ['checked' => TRUE],
        ],
        'checked' => [
          ':input[name="demo"]' => ['checked' => TRUE],
        ]
      ];
    }
    unset($form['actions']['skip']);
    // The following line is here to avoid php warning when the form is
    // displayed in commerce_kickstart installer theme.
    $form['#title'] = '';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    global $install_state;
    parent::submitForm($form, $form_state);

    // If demo was selected, we force recipes selection to only match demo.
    if ($demo = $form_state->getValue('demo', [])) {
      $install_state['parameters']['recipes'] = is_array($demo) ? $demo : [$demo];
    }

    // Indicate that we're done with this form.
    // @see ::toInstallTask()
    $install_state['parameters']['add_ons'] = INSTALL_TASK_SKIP;
  }

  public static function valueCallback(&$element, $input, FormStateInterface $form_state): array {
    // If the input was a pipe-separated string or `*`, transform it -- this is
    // for compatibility with `drush site:install`.
    if (is_string($input)) {
      $selections = $input === '*'
        ? array_keys($element['#options'])
        : array_map('trim', explode('|', $input));

      $input = array_combine($selections, $selections);
    }
    return Checkboxes::valueCallback($element, $input, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getChoices(): iterable {
    global $install_state;
    $choices = [];

    foreach ($install_state['profile_info']['recipes']['optional'] ?? [] as $key => $value) {
      // For backwards compatibility, each choice can either be a flat array of
      // package names (in which case the key is the human-readable name), or
      // it can be an associative array with `name` and `packages` elements (the
      // best practice).
      if (array_is_list($value)) {
        $value = [
          'name' => $key,
          'packages' => $value,
          'description' => NULL,
        ];
      }
      // Allow the name to be a translatable string, which won't happen unless
      // we pass it through the translation system.
      $value['name'] = t($value['name']);
      $choices[$key] = $value;
    }
    return $choices;
  }
}
