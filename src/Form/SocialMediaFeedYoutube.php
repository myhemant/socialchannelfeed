<?php

namespace Drupal\socialmediafeed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class SocialMediaFeedYoutube extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'socialmediafeed_youtube_form';
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
    //Youtube.
    // Enable/Disable Youtube feed
    $form['youtube_enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Youtube Feed'),
      '#default_value' => $config->get('youtube_enable'),
    );

    $form['showfeedlink'] = array(
      '#type' => 'markup',
      '#markup' => '<a href="' . $viewpageurl . '" target="_blannk">' . 
      $this->t('View Feed Page') . '</a>',
    );

    $form['youtube'] = array(
      '#type' => 'details',
      '#title' => $this->t('Youtube settings'),
      '#description' => $this->t('Youtube config settings'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
    );
    $form['youtube']['settings'] = $this->youtube_config_form($config);

    return parent::buildForm($form, $form_state);
  }

  //Youtube config form.
  protected function youtube_config_form($config) {
    // Non-authenticated settings form.
    $oauth_url = "https://accounts.google.com/o/oauth2/v2/auth";
    $access_key = $config->get('youtube_social_feed_api_key');
    // Access token request in process.
    if (isset($_GET['code']) && $_GET['code'] != '' &&
        isset($_GET['state']) && $_GET['state'] == $config->get('youtube_state')) {
      if ($access_key == '') {

        // build the HTTP GET query
        $body = array(
          'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
          'form_params' => array(
            "client_id" => $config->get('youtube_social_feed_client_id'),
            "client_secret" => $config->get('youtube_social_feed_client_secret'),
            "grant_type" => "authorization_code",
            "redirect_uri" => $config->get('youtube_social_feed_redirect_uri'),
            "code" => $_GET['code'],
          ),
          'verify' => FALSE,
          'proxy' => $config->get('proxy'),
        );

        try {
          $client = \Drupal::httpClient();
          $response = $client->post("https://www.googleapis.com/oauth2/v4/token", $body);
          $auth = json_decode($response->getBody());
        } catch (RequestException $e) {
          watchdog_exception('socialmediafeed:youtube', $e);
        } catch (ConnectException $e) {
          watchdog_exception('socialmediafeed:youtube', $e);
        } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
          watchdog_exception('socialmediafeed:youtube', $e);
        } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
          watchdog_exception('socialmediafeed:youtube', $e);
        } catch (Guzzle\Http\Exception\BadResponseException $e) {
          watchdog_exception('socialmediafeed:youtube', $e);
        } catch (Exception $e) {
          watchdog_exception('socialmediafeed:youtube', $e);
        }

        if (empty($auth->error_message)) {
          \Drupal::configFactory()->getEditable('socialmediafeed.config')
              ->set('youtube_social_feed_api_key', $auth->access_token)
              ->set('youtube_social_feed_expire_in', (time()+$auth->expires_in))
              ->set('youtube_social_feed_refreshToken', $auth->refresh_token)
              ->save();
          $access_key = $auth->access_token;
          drupal_set_message(t('YouTube authentication successful'));
        }
        else {
          drupal_set_message($auth->error_message, 'error');
        }
      }
    }
    elseif (array_key_exists('code', $_GET) && $_GET['code'] == '') {

      // Remove api key for re-authentication.
      \Drupal::configFactory()->getEditable('socialmediafeed.config')
          ->set('youtube_social_feed_api_key', null)
          ->save();
      // Unset variable for form.
      $access_key = '';
    }

    $form = array();
    if ($access_key == '') {

      // Non-authenticated settings form.
      $form['youtube_social_feed_client_id'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#required' => TRUE,
        '#default_value' => $config->get('youtube_social_feed_client_id'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('You must register an LiinkedIn client key to use 
          this module. You can register a client by 
          <a href="https://console.developers.google.com/" 
          target="_blank">clicking here</a>.'),
      );
      $form['youtube_social_feed_client_secret'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret'),
        '#required' => TRUE,
        '#default_value' => $config->get('youtube_social_feed_client_secret'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('The client secret can be found after creating an
          Youtube client in the API console.'),
      );
      $form['youtube_social_feed_redirect_uri'] = array(
        '#type' => 'textfield',
        '#title' => t('Youtube Redirect URI'),
        '#required' => TRUE,
        '#default_value' => $config->get('youtube_social_feed_redirect_uri'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => t('Set the redirect URI to :url', array(
          ':url' => \Drupal::url('socialmediafeed.form'),
        )),
      );
      $token = md5(uniqid(rand(), TRUE));
      // Remove api key for re-authentication.
      \Drupal::configFactory()->getEditable('socialmediafeed.config')
          ->set('youtube_state', $token)
          ->save();

      $option = array(
        'query' => array(
          'client_id' => $config->get('youtube_social_feed_client_id'),
          'redirect_uri' => $config->get('youtube_social_feed_redirect_uri'),
          'response_type' => 'code',
          'scope' => 'https://www.googleapis.com/auth/youtube',
          'state' => $token,
          'access_type' => 'offline',
          "prompt" => "consent",
          'include_granted_scopes' => "true",
        )
      );
      $url = Url::fromUri($oauth_url, $option);
      if ($config->get('youtube_social_feed_client_id') != '' && $config->get('youtube_social_feed_redirect_uri') != '') {
        $form['authenticate'] = array(
          '#markup' => \Drupal::l(
              t('Click here to authenticate via Google and create an access token'), $url
          )
        );
      }
    }
    else {

      // Authenticated user settings form.
      $form['youtube_social_feed_api_key'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('YouTube API Key'),
        '#default_value' => $config->get('youtube_social_feed_api_key'),
        '#size' => 60,
        '#maxlength' => 255,
        '#disabled' => TRUE,
        '#description' => $this->t('Stored access key for accessing the API key'),
      );
      $url = Url::fromRoute('socialmediafeed.youtube', array(
            'code' => '',
          ));
      $form['authenticate'] = array(
        '#markup' => \Drupal::l(
            $this->t('Click here to remove the access key and re-authenticate via Google'), $url
        ),
      );
    }
//    $form['youtube_social_feed_page_id'] = array(
//        '#type' => 'textfield',
//        '#title' => t('Youtube User Name'),
//        '#default_value' => $config->get('youtube_social_feed_page_id'),
//        '#size' => 60,
//        '#maxlength' => 255,
//        '#required' => TRUE,
//        '#description' => t('Stored access key for accessing the API key'),
//      );
    $form['youtube_item_to_fetch'] = array(
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of feed to fetch'),
      '#min' => 10,
      '#max' => 50,
      '#default_value' => $config->get('youtube_item_to_fetch'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $request = $this->config('socialmediafeed.config');
    // Set the submitted configuration setting
    //Youtube api 
    if ($form_state->getValue('youtube_social_feed_client_id')) {
      $request->set('youtube_social_feed_client_id', $form_state->getValue('youtube_social_feed_client_id'));
    }
    if ($form_state->getValue('youtube_social_feed_client_secret')) {
      $request->set('youtube_social_feed_client_secret', $form_state->getValue('youtube_social_feed_client_secret'));
    }
    if ($form_state->getValue('youtube_social_feed_redirect_uri')) {
      $request->set('youtube_social_feed_redirect_uri', $form_state->getValue('youtube_social_feed_redirect_uri'));
    }
    $request->set('youtube_enable', $form_state->getValue('youtube_enable'));
    $request->set('youtube_item_to_fetch', $form_state->getValue('youtube_item_to_fetch'));
    $request->save();

    parent::submitForm($form, $form_state);
  }

}