<?php

namespace Drupal\bos_google_cloud\Services;

use Drupal;
use Drupal\bos_core\Controllers\Curl\BosCurlControllerBase;
use Drupal\bos_google_cloud\GcGenerationConfig;
use Drupal\bos_google_cloud\GcGenerationPrompt;
use Drupal\bos_google_cloud\GcGenerationURL;
use Drupal\bos_google_cloud\GcGenerationPayload;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\bos_core\Controllers\Settings\CobSettings;
use Drupal\Core\Render\Markup;
use Exception;

/**
  class GcConversation
  Creates a gen-ai conversation service for bos_google_cloud

  david 01 2024
  @file docroot/modules/custom/bos_components/modules/bos_google_cloud/src/Services/GcConversation.php
*/

class GcTranslation extends BosCurlControllerBase implements GcServiceInterface {

  private GcGenerationConfig $generation_config;

    /**
   * Logger object for class.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $log;

  /**
   * Config object for class.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  protected array $settings;

  /**
   * @var GcAuthenticator Google Cloud Authenication Service.
   */
  protected GcAuthenticator $authenticator;

    public function __construct(LoggerChannelFactory $logger, ConfigFactory $config) {

    // Load the service-supplied variables.
    $this->log = $logger->get('GcAuthenticator');
    $this->config = $config->get("bos_google_cloud.settings");

    $this->settings = CobSettings::getSettings("GCAPI_SETTINGS", "bos_google_cloud");

    // Create an authenticator using service account 1.
    $this->authenticator = new GcAuthenticator($this->settings['translate']['service_account'] ?? GcAuthenticator::SVS_ACCOUNT_LIST[0]);

    // Use default generation config.
    $this->setGenerationConfig(new GcGenerationConfig());

    // Do the CuRL initialization in BosCurlControllerBase.
    parent::__construct();

  }

  /**
   * Set the service_account, overriding the default.
   *
   * @param string $service_account A valid service account.
   *
   * @return $this
   * @throws \Exception
   */
  public function setServiceAccount(string $service_account):GcTranslation {
    if (!$this->authenticator->validateServiceAccount($service_account)) {
      throw new Exception("Service account does not exist.");
    }
    $this->settings["translate"]['service_account'] = $service_account;
    return $this;
  }

  /**
   * Set the Generation Config settings overriding the defaults.
   *
   * @param GcGenerationConfig $generationConfig Config to set
   *
   * @return $this
   */
  public function setGenerationConfig(GcGenerationConfig $generationConfig): GcTranslation {
    $this->generation_config = $generationConfig;
    return $this;
  }

  /**
   * Translates a piece of text into another language, using a pre-defined
   * prompt.
   *
   * @param array $parameters Array containing "text" URLencode text to be summarized, "lang" the output language and "prompt" A search type prompt.
   *
   * @return string
   *
   * @see https://cloud.google.com/vertex-ai/docs/generative-ai/model-reference/gemini#request_body Ref for generationConfig array format
   */
  public function execute(array $parameters = []): string {

    $settings = $this->settings["translate"] ?? [];

    try {
      $headers = [
        "Authorization" => $this->authenticator->getAccessToken($settings['service_account'], "Bearer")
      ];
    }
    catch (Exception $e) {
      $this->error = $e->getMessage() ?? "Error getting access token.";
      return "";
    }

    if (empty($parameters["text"])) {
      $this->error = "A piece of text to translate is required.";
      return "";
    }

    $parameters["prompt"] = $parameters["prompt"] ?? "default";
    $parameters["lang"] = $parameters["lang"] ?? "default";

    // @see https://cloud.google.com/vertex-ai/docs/generative-ai/model-reference/gemini
    $url = GcGenerationURL::build(GcGenerationPayload::PREDICTION, $settings);

    try {
      $options = [
        "prediction" => [
          GcGenerationPrompt::getPromptText("base", $parameters["prompt"]),
          "You are also a translation expert specializing in translating from English to other languages.",
          GcGenerationPrompt::getPromptText("translation", $parameters["lang"]),
          $parameters["text"]
        ],
        "generation_config" => $this->generation_config->getConfig()
      ];
      if (!$payload = GcGenerationPayload::build(GcGenerationPayload::PREDICTION, $options)) {
        $this->error = "Could not build Payload";
        return $this->error;
      }
    }
    catch (Exception $e) {
      $this->error = $e->getMessage();
      return "";
    }

    $results = $this->post($url, $payload, $headers);

    if ($this->http_code() == 200) {

      $model_id = $settings["model_id"];

      $this->response[$model_id]["content"] = "";

      foreach ($results as $key => $result){

        if (isset($result["usageMetadata"])) {
          $this->response[$model_id]["usageMetadata"] = $results[0]["usageMetadata"] ?? [];
        }

        $this->loadSafetyRatings($result["candidates"][0]["safetyRatings"], $key, $model_id);

        if (isset($result["candidates"][0]["content"]["parts"][0]["text"])) {
          $this->response[$model_id]["content"] .= $result["candidates"][0]["content"]["parts"][0]["text"];
        }

      }

      if (empty($this->response[$model_id]["content"]) || $this->error()) {
        $this->error() || $this->error = "Unexpected response from $model_id";
        return "";
      }

      return $this->response[$model_id]["content"];

    }
    else {
      return "";
    }

  }

  /**
   * Update the $this->response["search"]["ratings"] array if the safety scores
   * in $ratings are higher (less safe) than those already stored.
   *
   * @param array $ratings The safetyRatings from a gemini ::predict call.
   * @param int $ord The ordinal for the response part. When =0 expect class
   * *  array to be empty;
   * * @param string $model_id The model_id for reporting.
   *
   * @return void
   */
  private function loadSafetyRatings(array $ratings, int $ord, string $model_id): void {

    if (!isset($this->response[$model_id]["ratings"])) {
      $this->response[$model_id]["ratings"] = [];
    }

    $ratings_field = $this->response[$model_id]["ratings"];

    foreach($ratings as $rating) {

      if ($ord == 0 || empty($ratings_field[$rating["category"]])) {
        $ratings_field[$rating["category"]] = $rating;
      }
      else {
        if ($rating["probabilityScore"] > ($ratings_field[$rating["category"]]["probabilityScore"] ?? 0)) {
          $ratings_field[$rating["category"]]["probability"] = $rating["probability"];
          $ratings_field[$rating["category"]]["probabilityScore"] = $rating["probabilityScore"];
        }
        if ($rating["severityScore"] > ($ratings_field[$rating["category"]]["severityScore"] ?? 0)) {
          $ratings_field[$rating["category"]]["severity"] = $rating["severity"];
          $ratings_field[$rating["category"]]["severityScore"] = $rating["severityScore"];
        }
        if ($rating["probabilityScore"] > ($ratings_field["OVERALL_HARM"]["probabilityScore"] ?? 0)) {
          $ratings_field["OVERALL_HARM"]["probability"] = $rating["probability"];
          $ratings_field["OVERALL_HARM"]["probabilityScore"] = $rating["probabilityScore"];
        }
        if ($rating["severityScore"] > ($ratings_field["OVERALL_HARM"]["severityScore"] ?? 0)) {
          $ratings_field["OVERALL_HARM"]["severity"] = $rating["severity"];
          $ratings_field["OVERALL_HARM"]["severityScore"] = $rating["severityScore"];
        }
      }

    }

    $this->response[$model_id]["ratings"] = $ratings_field;

  }

  /**
   * @inheritDoc
   */
  public function buildForm(array &$form): void {

    $project_id="612042612588";
    $model_id="gemini-pro";
    $location_id="us-east4";
    $endpoint="https://$location_id-aiplatform.googleapis.com";

    $svs_accounts = [];
    foreach ($this->settings["auth"] as $name => $value) {
      if ($name) {
        $svs_accounts[$name] = $name;
      }
    }

    $settings = $this->settings['translate'] ?? [];

    $form = $form + [

      'project_id' => [
        '#type' => 'textfield',
        '#title' => t('The project ID to use'),
        '#description' => t(''),
        '#default_value' => $settings['project_id'] ?? $project_id,
        '#required' => TRUE,
        '#attributes' => [
          "placeholder" => 'e.g. ' . $project_id,
        ],
      ],
      'model_id' => [
        '#type' => 'textfield',
        '#title' => t('The Language Model to use:'),
        '#description' => t(''),
        '#default_value' => $settings['model_id'] ?? $model_id,
        '#required' => TRUE,
        '#attributes' => [
          "placeholder" => 'e.g. ' . $model_id,
        ],
      ],
      'location_id' => [
        '#type' => 'textfield',
        '#title' => t('The Model Location to use (= a "global")'),
        '#description' => t(''),
        '#default_value' => $settings['location_id'] ?? $location_id,
        '#required' => TRUE,
        '#disabled' => TRUE,
        '#attributes' => [
          "placeholder" => 'e.g. ' . $location_id,
        ],
      ],
      'endpoint' => [
        '#type' => 'textfield',
        '#title' => t('The endpoint to use'),
        '#description' => t(''),
        '#default_value' => $settings['endpoint'] ?? $endpoint,
        '#required' => TRUE,
        '#attributes' => [
          "placeholder" => 'e.g. ' . $endpoint,
        ],
      ],

      'service_account' => [
        '#type' => 'select',
        '#title' => t('The default service account to use'),
        '#description' => t('This default can be overridden using the API.'),
        '#default_value' => $settings['service_account'] ?? ($svs_accounts[0] ?? ""),
        '#options' => $svs_accounts,
        '#required' => TRUE,
        '#attributes' => [
          "placeholder" => 'e.g. ' . ($svs_accounts[0] ?? "No Service Accounts!"),
        ],
      ],
      'test_wrapper' => [
        'test_button' => [
          '#type' => 'button',
          "#value" => t('Test Translator'),
          '#attributes' => [
            'class' => ['button', 'button--primary'],
            'title' => "Test the provided configuration for this service"
          ],
          '#access' => TRUE,
          '#ajax' => [
            'callback' => [$this, 'ajaxTestService'],
            'event' => 'click',
            'wrapper' => 'edit-translate-result',
            'disable-refocus' => TRUE,
            'progress' => [
              'type' => 'throbber',
            ]
          ],
          '#suffix' => '<span id="edit-translate-result"></span>',
        ],
      ],
    ];
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array $form, FormStateInterface $form_state): void {

    $values = $form_state->getValues()["google_cloud"]['services_wrapper']['translate'];
    $config = Drupal::configFactory()->getEditable("bos_google_cloud.settings");

    if ($config->get("translate.project_id") != $values['project_id']
      ||$config->get("translate.model_id") != $values['model_id']
      ||$config->get("translate.service_account") != $values['service_account']
      ||$config->get("translate.location_id") != $values['location_id']
      ||$config->get("translate.endpoint") != $values['endpoint']) {
      $config->set("translate.project_id", $values['project_id'])
        ->set("translate.model_id", $values['model_id'])
        ->set("translate.location_id", $values['location_id'])
        ->set("translate.service_account", $values['service_account'])
        ->set("translate.endpoint", $values['endpoint'])
        ->save();
    }

  }

  /**
   * @inheritDoc
   */
  public function validateForm(array $form, FormStateInterface &$form_state): void {
    // not required
  }

  /**
   * Ajax callback to test Translation.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function ajaxTestService(array &$form, FormStateInterface $form_state): array {

    $values = $form_state->getValues()["google_cloud"]['services_wrapper']['translate'];
    $translater = Drupal::service("bos_google_cloud.GcTranslate");

    $options = [
      "text" => "This is some text to translate.",
      "prompt" => "default",
      'lang' => "french"
    ];

    unset($values["test_wrapper"]);
    $translater->settings = CobSettings::array_merge_deep($translater->settings, ["translate" => $values]);
    $result = $translater->execute($options);

    if (!empty($result)) {
      return ["#markup" => Markup::create("<span id='edit-translate-result' style='color:green'><b>&#x2714; Success:</b> Authentication and Service Config are OK.</span>")];
    }
    else {
      return ["#markup" => Markup::create("<span id='edit-translate-result' style='color:red'><b>&#x2717; Failed:</b> {$translater->error()}</span>")];
    }

  }

}
