<?php

namespace Drupal\socialmediafeed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class SocialMediaFeedFacebook extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'socialmediafeed_facebook_form';
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
        $form['facebook_enable'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Enable Facebook'),
            '#default_value' => $config->get('facebook_enable'),
        );

        $form['showfeedlink'] = array(
            '#type' => 'markup',
            '#markup' => '<a href="' . $viewpageurl . '" target="_blannk">' . $this->t('View Feed Page') . '</a>',
        );
        //Facebook.
        $form['facebook'] = array(
            '#type' => 'details',
            '#title' => $this->t('Facebook settings'),
            '#description' => $this->t('Facebook config settings'),
            '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
        );
        $form['facebook']['settings'] = $this->fb_config_form($config);
        return parent::buildForm($form, $form_state);
    }

    //Facebook config form.
    protected function fb_config_form($config) {
        // Non-authenticated settings form.
        $form['fb_social_feed_client_id'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Facebook Client ID'),
            '#default_value' => $config->get('fb_social_feed_client_id'),
            '#required' => TRUE,
            '#size' => 60,
            '#maxlength' => 255,
            '#description' => $this->t('You must register an facebook app to use 
        this module. You can register a app by 
        <a href="https://developers.facebook.com/apps/" 
        target="_blank">clicking here</a>.'),
        );
        $form['fb_social_feed_client_secret'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Facebook Client Secret'),
            '#default_value' => $config->get('fb_social_feed_client_secret'),
            '#required' => TRUE,
            '#size' => 60,
            '#maxlength' => 255,
            '#description' => $this->t('The client secret can be found after creating 
        an Facebook app in the API console.'),
    );
    
    $form['fb_page_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Facebook page ID'),
      '#required' => TRUE,
      '#default_value' => $config->get('fb_page_id'),
      '#size' => 60,
      '#maxlength' => 255,
      '#description' => $this->t('Facebook page id.'),
    );
    $form['fb_item_to_fetch'] = array(
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of feed to fetch'),
      '#min' => 10,
      '#max' => 100,
      '#default_value' => $config->get('fb_item_to_fetch'),
    );
    $form['fb_des_trim'] = array(
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('DEscription trim length'),
      '#min' => 100,
      '#max' => 300,
      '#default_value' => $config->get('fb_des_trim'),
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
      ->set('facebook_enable', 
         $form_state->getValue('facebook_enable')) 
      ->set('fb_social_feed_client_id', 
         $form_state->getValue('fb_social_feed_client_id'))
      ->set('fb_social_feed_client_secret', 
         $form_state->getValue('fb_social_feed_client_secret'))
      ->set('fb_page_id', 
         $form_state->getValue('fb_page_id'))      
      ->set('fb_item_to_fetch', 
         $form_state->getValue('fb_item_to_fetch'))
      ->set('fb_des_trim', 
         $form_state->getValue('fb_des_trim'))
      ->save();
    
    parent::submitForm($form, $form_state);
  }
}