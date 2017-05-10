<?php

namespace Drupal\socialmediafeed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class SocialMediaFeedLinkedin extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'socialmediafeed_linkedin_form';
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
    //LinkedIn.
    // Enable/Disable linkedin feed
    $form['linkedin_enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Linkedin Feed'),
      '#default_value' => $config->get('linkedin_enable'),
    );
    $form['showfeedlink'] = array(
      '#type' => 'markup',
      '#markup' => '<a href="' . $viewpageurl . '" target="_blannk">' . 
      $this->t('View Feed Page') . '</a>',
    );

    $form['linkedin'] = array(
      '#type' => 'details',
      '#title' => $this->t('LinkedIn settings'),
      '#description' => $this->t('LinkedIn config settings'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
    );
    $form['linkedin']['settings'] = $this->linkedin_config_form($config);

    return parent::buildForm($form, $form_state);
  }

  //Instagram config form.
  protected function linkedin_config_form($config) {

    $access_key = $config->get('linkedin_social_feed_api_key');
    // Access token request in process.
    if (isset($_GET['code']) && $_GET['code'] != '' &&
        isset($_GET['state']) && $_GET['state'] == $config->get('linkedin_state')) {
      if ($access_key == '') {
        $api_url = "https://www.linkedin.com/oauth/v2/accessToken";
        // build the HTTP GET query
        $body = array(
          'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
          'form_params' => array(
            "client_id" => $config->get('linkedin_social_feed_client_id'),
            "client_secret" => $config->get('linkedin_social_feed_client_secret'),
            "grant_type" => "authorization_code",
            "redirect_uri" => $config->get('linkedin_social_feed_redirect_uri'),
            "code" => $_GET['code'],
          ),
          'verify' => FALSE,
          'proxy' => $config->get('proxy'), // was through error when authenticate linkedin
        );

        try {
          $client = \Drupal::httpClient();
          $response = $client->post($api_url, $body);
          $auth = json_decode($response->getBody());
        } catch (RequestException $e) {
          watchdog_exception('socialmediafeed', $e->getMessage());
        }

        if (empty($auth->error_message)) {
          \Drupal::configFactory()->getEditable('socialmediafeed.config')
              ->set('linkedin_social_feed_api_key', $auth->access_token)
              ->set('linkedin_social_feed_expire_in', $auth->expires_in)
              ->save();
          $access_key = $auth->access_token;
          drupal_set_message(t('LinkedIn authentication successful'));
        }
        else {
          drupal_set_message($auth->error_message, 'error');
        }
      }
    }
    elseif (array_key_exists('code', $_GET) && $_GET['code'] == '') {

      // Remove api key for re-authentication.
      \Drupal::configFactory()->getEditable('socialmediafeed.config')
          ->set('linkedin_social_feed_api_key', null)
          ->save();
      // Unset variable for form.
      $access_key = '';
    }

    $form = array();
    if ($access_key == '') {

      // Non-authenticated settings form.
      $form['linkedin_social_feed_client_id'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('LiinkedIn Client ID'),
        '#required' => TRUE,
        '#default_value' => $config->get('linkedin_social_feed_client_id'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('You must register an LiinkedIn client key to use 
          this module. You can register a client by 
          <a href="https://www.linkedin.com/developer/apps" 
          target="_blank">clicking here</a>.'),
      );
      $form['linkedin_social_feed_client_secret'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('LinkedIn Client Secret'),
        '#required' => TRUE,
        '#default_value' => $config->get('linkedin_social_feed_client_secret'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('The client secret can be found after creating an
          LinkedIn client in the API console.'),
      );
      $form['linkedin_social_feed_redirect_uri'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('LinkedIn Redirect URI'),
        '#required' => TRUE,
        '#default_value' => $config->get('linkedin_social_feed_redirect_uri'),
        '#size' => 60,
        '#maxlength' => 255,
        '#description' => $this->t('Set the redirect URI to :url', array(
          ':url' => \Drupal::url('socialmediafeed.form'),
        )),
      );
      $token = md5(uniqid(rand(), TRUE));
      // Remove api key for re-authentication.
      \Drupal::configFactory()->getEditable('socialmediafeed.config')
          ->set('linkedin_state', $token)
          ->save();

      $option = array(
        'query' => array(
          'client_id' => $config->get('linkedin_social_feed_client_id'),
          'redirect_uri' => $config->get('linkedin_social_feed_redirect_uri'),
          'response_type' => 'code',
          'scope' => 'r_basicprofile rw_company_admin',
          'state' => $token,
        )
      );
      $url = Url::fromUri('https://www.linkedin.com/oauth/v2/authorization', $option);
      if ($config->get('linkedin_social_feed_client_id') != '' && $config->get('linkedin_social_feed_redirect_uri') != '') {
        $form['authenticate'] = array(
          '#markup' => \Drupal::l(
              t('Click here to authenticate via LinkedIn and create an access token'), $url
          )
        );
      }
    }
    else {

      // Authenticated user settings form.
      $form['linkedin_social_feed_api_key'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('LinkedIn API Key'),
        '#default_value' => $config->get('linkedin_social_feed_api_key'),
        '#size' => 60,
        '#maxlength' => 255,
        '#disabled' => TRUE,
        '#description' => $this->t('Stored access key for accessing the API key'),
      );
      $url = Url::fromRoute('socialmediafeed.linkedin', array(
            'code' => '',
          ));
      $form['authenticate'] = array(
        '#markup' => \Drupal::l(
            t('Click here to remove the access key and re-authenticate via LinkedIn'), $url
        ),
      );
      $form['linkedin_social_feed_page_id'] = $this->linkedin_companies();
    }

    $form['linkedin_item_to_fetch'] = array(
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of feed to fetch'),
      '#min' => 10,
      '#max' => 100,
      '#default_value' => $config->get('linkedin_item_to_fetch'),
    );
    return $form;
  }

  protected function linkedin_companies() {
    $cid = 'socialmediafeed:linkedin_comp';
    $config = $this->config('socialmediafeed.config');
    $data = NULL;
    if ($config->get('enable_cache') && $cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
    }
    else {
      $api_url = "https://api.linkedin.com/v1/companies";
      $bearer_token = $config->get('linkedin_social_feed_api_key');
      // build the HTTP GET query
      $comp = array();
      if ($bearer_token) {
        $body = array(
          'headers' => array(
            "Authorization " => "Bearer " . $bearer_token,
          ),
          'query' => array(
            "format" => "json",
            "is-company-admin" => "true",
          ),
          'proxy' => $config->get('proxy'),
          'verify' => FALSE,
        );

        try {
          $client = \Drupal::httpClient();
          $response = $client->get($api_url, $body);
          $linkedin_feed = json_decode($response->getBody()->getContents(), TRUE);

          //$comp = array("total" => $linkedin_feed['_total']);
          foreach ($linkedin_feed['values'] as $key => $linkedin_value) {
            $comp[$linkedin_value['id']] = $linkedin_value['name'];
          }
        } catch (ConnectException $e) {
          watchdog_exception('socialmediafeed:linkedin', $e);
        } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
          watchdog_exception('socialmediafeed:linkedin', $e);
        } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
          watchdog_exception('socialmediafeed:linkedin', $e);
        } catch (Guzzle\Http\Exception\BadResponseException $e) {
          watchdog_exception('socialmediafeed:linkedin', $e);
        } catch (RequestException $e) {
          watchdog_exception('socialmediafeed:linkedin', $e);
        } catch (Exception $e) {
          watchdog_exception('socialmediafeed:linkedin', $e);
        }
      }
      $data = $comp;

      \Drupal::cache()->set($cid, $comp, strtotime('+1 hour'));
    }

    //unset($data['total']);
    $this->config('socialmediafeed.config')
        ->set('linkedin_social_feed_page_id', key($data))->save();
    return array(
      '#type' => 'select',
      '#title' => $this->t('LinkedIn Page ID'),
      '#options' => $data,
      '#default_value' => array($config->get('linkedin_social_feed_page_id')),
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#description' => $this->t('Stored access key for accessing the API key'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $request = $this->config('socialmediafeed.config');
    // Set the submitted configuration setting    
    //Linkedin api
    if ($form_state->getValue('linkedin_social_feed_client_id')) {
      $request->set('linkedin_social_feed_client_id', $form_state->getValue('linkedin_social_feed_client_id'));
    }
    if ($form_state->getValue('linkedin_social_feed_client_secret')) {
      $request->set('linkedin_social_feed_client_secret', $form_state->getValue('linkedin_social_feed_client_secret'));
    }
    if ($form_state->getValue('linkedin_social_feed_redirect_uri')) {
      $request->set('linkedin_social_feed_redirect_uri', $form_state->getValue('linkedin_social_feed_redirect_uri'));
    }
    $request->set('linkedin_social_feed_page_id', $form_state->getValue('linkedin_social_feed_page_id'));
    $request->set('linkedin_enable', $form_state->getValue('linkedin_enable'));
    $request->set('linkedin_item_to_fetch', $form_state->getValue('linkedin_item_to_fetch'));
    $request->save();

    parent::submitForm($form, $form_state);
  }
}