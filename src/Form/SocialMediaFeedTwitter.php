<?php

namespace Drupal\socialmediafeed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class SocialMediaFeedTwitter extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'socialmediafeed_twitter_form';
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
    //Twitter.
    // Enable/Disable Twitter feed
    $form['twitter_enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Twitter Feed'),
      '#default_value' => $config->get('twitter_enable'),
    );

    $form['showfeedlink'] = array(
      '#type' => 'markup',
      '#markup' => '<a href="' . $viewpageurl . '" target="_blannk">' . 
      $this->t('View Feed Page') . '</a>',
    );

    $form['twitter'] = array(
      '#type' => 'details',
      '#title' => $this->t('Twitter settings'),
      '#description' => $this->t('Twitter config settings'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
    );
    $form['twitter']['settings'] = $this->twitter_config_form($config);

    return parent::buildForm($form, $form_state);
  }

  //Twitter config form.
  protected function twitter_config_form($config) {
    // Non-authenticated settings form.
    $form['twitter_social_feed_client_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Twitter Client ID'),
      '#default_value' => $config->get('twitter_social_feed_client_id'),
      '#size' => 60,
      '#required' => TRUE,
      '#maxlength' => 255,
      '#description' => $this->t('You must register an twitter app to use 
        this module. You can register a app by 
        <a href="https://apps.twitter.com/" 
        target="_blank">clicking here</a>.'),
    );
    $form['twitter_social_feed_client_secret'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Twitter Client Secret'),
      '#default_value' => $config->get('twitter_social_feed_client_secret'),
      '#size' => 60,
      '#required' => TRUE,
      '#maxlength' => 255,
      '#description' => $this->t('The client secret can be found after creating an 
        Twitter app in the API console.'),
    );
    $form['twitter_social_feed_screen_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Twitter user screen name'),
      '#required' => TRUE,
      '#default_value' => $config->get('twitter_social_feed_screen_name'),
      '#size' => 60,
      '#maxlength' => 255,
      '#description' => $this->t('Provide user screen name to fetch data from his profile'),
    );
    $form['twitter_item_to_fetch'] = array(
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Number of feed to fetch'),
      '#min' => 10,
      '#max' => 200,
      '#default_value' => $config->get('twitter_item_to_fetch'),
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
        //Twitter api
        ->set('twitter_enable', $form_state->getValue('twitter_enable'))
        ->set('twitter_social_feed_client_id', $form_state->getValue('twitter_social_feed_client_id'))
        ->set('twitter_social_feed_client_secret', $form_state->getValue('twitter_social_feed_client_secret'))
        ->set('twitter_social_feed_screen_name', $form_state->getValue('twitter_social_feed_screen_name'))
        ->set('twitter_item_to_fetch', $form_state->getValue('twitter_item_to_fetch'))
        ->save();

    parent::submitForm($form, $form_state);
  }

}