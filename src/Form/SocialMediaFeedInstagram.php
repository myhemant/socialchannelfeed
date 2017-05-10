<?php

namespace Drupal\socialmediafeed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class SocialMediaFeedInstagram extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'socialmediafeed_instagram_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
    'socialmediafeed.config',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('socialmediafeed.config');
    $viewpageurl = $GLOBALS['base_url'] . "/social-media-feed";
    //Instagram.
    $form['instagram_enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Instagram Feed'),
      '#default_value' => $config->get('instagram_enable'),
    );

    $form['showfeedlink'] = array(
      '#type' => 'markup',
      '#markup' => '<a href="' . $viewpageurl . '" target="_blannk">' . $this->t('View Feed Page') . '</a>',
    );

    $form['instagram'] = array(
      '#type' => 'details',
      '#title' => $this->t('Instagram settings'),
      '#description' => t('Instagram config settings'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
    );
    $form['instagram']['settings'] = $this->instagram_config_form($config);

    return parent::buildForm($form, $form_state);
  }

  //Instagram config form.
  protected function instagram_config_form($config) {
    $access_key = $config->get('instagram_social_feed_api_key');
    // Access token request in process.
    if (isset($_GET['code']) && $_GET['code'] != '') {
      if ($access_key == '') {
        $api_url = "https://api.instagram.com/oauth/access_token";
        // build the HTTP GET query
        $body = array(
          'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
          'form_params' => array(
            "client_id" => $config->get('instagram_social_feed_client_id'),
            "client_secret" => $config->get('instagram_social_feed_client_secret'),
            "grant_type" => "authorization_code",
            "redirect_uri" => $config->get('instagram_social_feed_redirect_uri'),
            "code" => $_GET['code'],
          ),
          'verify' => FALSE,
          'proxy' => $config->get('proxy'),
        );

        try {
          $client = \Drupal::httpClient();
          $response = $client->post($api_url, $body);
          $auth = json_decode($response->getBody());
        } catch (RequestException $e) {
          watchdog_exception('ge_careers_stats', $e->getMessage());
        }

        if (empty($auth->error_message)) {
          \Drupal::configFactory()->getEditable('socialmediafeed.config')
              ->set('instagram_social_feed_api_key', $auth->access_token)
              ->set('instagram_social_feed_user_id', $auth->user->id)
              ->set('instagram_social_feed_username', $auth->user->username)
              ->save();
          $access_key = $auth->access_token;
          drupal_set_message(t('Instagram authentication successful'));
        }
        else {
          drupal_set_message($auth->error_message, 'error');
        }
      }
    }
    elseif (array_key_exists('code', $_GET) && $_GET['code'] == '') {

      // Remove api key for re-authentication.
      \Drupal::configFactory()->getEditable('socialmediafeed.config')
          ->set('instagram_social_feed_api_key', null)
          ->save();
      // Unset variable for form.
      $access_key = '';
    }

    $form = array();
    if ($access_key == '') {

      // Non-authenticated settings form.
      $form['instagram_social_feed_client_id'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Instagram Client ID'),
        '#default_value' => $config->get('instagram_social_feed_client_id'),
        '#required' => TRUE,
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('You must register an Instagram client key 
          to use this module. You can register a client by 
          <a href="http://instagram.com/developer/clients/manage/" 
          target="_blank">clicking here</a>.'),
      );
      $form['instagram_social_feed_client_secret'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Instagram Client Secret'),
        '#required' => TRUE,
        '#default_value' => $config->get('instagram_social_feed_client_secret'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' =>$this-> t('The client secret can be found after 
          creating an Instagram client in the API console.'),
      );
      $form['instagram_social_feed_redirect_uri'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Instagram Redirect URI'),
        '#default_value' => $config->get('instagram_social_feed_redirect_uri'),
        '#required' => TRUE,
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('Set the redirect URI to :url', array(
          ':url' => \Drupal::url('socialmediafeed.form'),
        )),
      );
      $url = Url::fromUri('https://api.instagram.com/oauth/authorize/?client_id=' .
              $config->get('instagram_social_feed_client_id') .
              '&redirect_uri=' . $config->get('instagram_social_feed_redirect_uri') .
              '&response_type=code');
      if ($config->get('instagram_social_feed_client_id') != '' && 
          $config->get('instagram_social_feed_redirect_uri') != '') {
        $form['authenticate'] = array(
          '#markup' => \Drupal::l(
              t('Click here to authenticate via Instagram and create an access 
                token'), $url
          )
        );
      }
    }
    else {

      // Authenticated user settings form.
      $form['instagram_social_feed_api_key'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Instagram API Key'),
        '#default_value' => $config->get('instagram_social_feed_api_key'),
        '#size' => 60,
        '#maxlength' => 255,
        '#disabled' => TRUE,
        '#description' => $this->t('Stored access key for accessing the API key'),
      );

      $form['instagram_social_feed_username'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Instagram User name'),
        '#default_value' => $config->get('instagram_social_feed_username'),
        '#size' => 60,
        '#maxlength' => 255,
        '#disabled' => TRUE,
        '#description' => $this->t('Authorized user\'s user name'),
      );
      $url = Url::fromRoute('socialmediafeed.instagram', array(
            'code' => '',
          ));
      $form['authenticate'] = array(
        '#markup' => \Drupal::l(
            $this->t('Click here to remove the access key and re-authenticate 
              via Instagram'), $url
        ),
      );
    }
    $form['instagram_item_to_fetch'] = array(
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of feed to fetch'),
      '#min' => 10,
      '#max' => 100,
      '#default_value' => $config->get('instagram_item_to_fetch'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->config('socialmediafeed.config')
        // Set the submitted configuration setting
        //Instagram api
        ->set('instagram_enable', $form_state->getValue('instagram_enable'))
        ->set('instagram_social_feed_client_id', $form_state->getValue('instagram_social_feed_client_id'))
        ->set('instagram_social_feed_client_secret', $form_state->getValue('instagram_social_feed_client_secret'))
        ->set('instagram_social_feed_redirect_uri', $form_state->getValue('instagram_social_feed_redirect_uri'))
        ->set('instagram_item_to_fetch', $form_state->getValue('instagram_item_to_fetch'))
        ->save();

    parent::submitForm($form, $form_state);
  }

}