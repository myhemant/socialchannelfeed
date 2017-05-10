<?php

namespace Drupal\socialmediafeed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Configure example settings for this site.
 */
class SocialMediaFeedForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'socialmediafeedconfigForm';
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
    $form['showfeedlink'] = array(
      '#type' => 'markup',
      '#markup' => '<a href="' . $viewpageurl . '" target="_blannk">' . $this->t('View Feed Page') . '</a>',
    );
    $form['socialmediafeed'] = array(
      '#type' => 'details',
      '#title' => $this->t('Global configuration'),
      '#description' => $this->t('Settings'),
      '#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
    );
    $form['socialmediafeed']['settings'] = $this->config_form($config);

    return parent::buildForm($form, $form_state);
  }

  //Global config form.
  protected function config_form($config) {

    $form['item_to_show'] = array(
      '#type' => 'number',
      '#title' => $this->t('Number of item per page'),
      '#min' => 10,
      '#max' => 200,
      '#default_value' => $config->get('item_to_show'),
    );
    $form['proxy'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Proxy URL'),
      '#default_value' => $config->get('proxy'),
      '#size' => 60,
      '#maxlength' => 255,
      '#description' => $this->t('Provide proxy URL(Optional).'),
    );
    $form['cache_enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable cache'),
      '#default_value' => $config->get('cache_enable'),
    );
    $data = array(
      1 => '1 hour',
      2 => '3 hours',
      3 => '12 hours',
      4 => '1 day',
      5 => '3 days',
    );
    $form['cache_duration'] = array(
      '#type' => 'select',
      '#title' => $this->t('Cache duration'),
      '#options' => $data,
      '#default_value' => array($config->get('cache_duration')),
      '#multiple' => FALSE,
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
        ->set('item_to_show', $form_state->getValue('item_to_show'))
        ->set('proxy', $form_state->getValue('proxy'))
        ->set('cache_enable', $form_state->getValue('cache_enable'))
        ->set('cache_duration', $form_state->getValue('cache_duration'))
        ->save();

    parent::submitForm($form, $form_state);
  }

}