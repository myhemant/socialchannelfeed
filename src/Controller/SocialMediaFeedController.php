<?php

/**
 * @file
 * Contains \Drupal\socialmediafeed\Controller\SocialMediaFeedController.
 */

namespace Drupal\socialmediafeed\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\socialmediafeed\Controller\FacebookController;
use Drupal\socialmediafeed\Controller\TwitterController;
#use Drupal\socialmediafeed\Controller\InstagramController;
use Drupal\socialmediafeed\Controller\YoutubeController;
use Drupal\socialmediafeed\Controller\LinkedinController;
use Drupal\Core\Ajax\AjaxResponse;

//use GuzzleHttp\Exception\RequestException;

/**
 * Controller routines for test_api routes.
 */
class SocialMediaFeedController extends ControllerBase {

  /**
   * Callback for `my-api/get.json` API method.
   */
  protected $apidefault = array();
  protected $config;
  protected $socialmediafeeds = array();

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->config = \Drupal::config('socialmediafeed.config');
  }

  //Custom sorting function for array.
  private function sort_by_timestamp($a, $b) {
    return ($a['timestamp'] >= $b['timestamp']) ? -1 : 1;
  }

  //Remove unwanted key from rendered array.
  function traverseArray(&$array, $keys) {
    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        $this->traverseArray($value, $keys);
      }
      else {
        if (in_array($key, $keys)) {
          unset($array[$key]);
        }
      }
    }
  }


  //Remove unwanted key from rendered array.
  function getCacheDuration() {
    $cache = $this->config->get('cache_duration');
    if(!$cache) {
      $cache = 1;
    }
    $data = array(
      1 => '1 hour',
      2 => '3 hours',
      3 => '12 hours',
      4 => '1 day',
      5 => '3 days',
    );
    return strtotime("+" . $data[$cache]);
  }

  // Fetch data from all channels and provide rendererable array.
  public function getPosts($offset = 0, $num_per_page = null) {
    $cid = 'socialmediafeed:allposts';
    $data = NULL;
    if ($this->config->get('cache_enable') && $cache = \Drupal::cache()->get($cid)) {
      $this->socialmediafeeds = $cache->data;
    }
    else {
      if ($this->config->get('facebook_enable')) {
        $fb = new FacebookController();
        $a = $fb->getData();
        $this->socialmediafeeds = array_merge($this->socialmediafeeds, $a);
      }
      if ($this->config->get('twitter_enable')) {
        $twitter = new TwitterController();
        $a = $twitter->getData();
        $this->socialmediafeeds = array_merge($this->socialmediafeeds, $a);
      }
      if ($this->config->get('instagram_enable')) {
        $insta = new InstagramController();
        $a = $insta->getData();
        $this->socialmediafeeds = array_merge($this->socialmediafeeds, $a);
      }
      if ($this->config->get('youtube_enable')) {
        $youtube = new YoutubeController();
        $a = $youtube->getData();
        $this->socialmediafeeds = array_merge($this->socialmediafeeds, $a);
      }
      if ($this->config->get('linkedin_enable')) {
        $linedin = new LinkedinController();
        $a = $linedin->getData();
        $this->socialmediafeeds = array_merge($this->socialmediafeeds, $a);
      }
      usort($this->socialmediafeeds, array($this, 'sort_by_timestamp'));
      $this->traverseArray($this->socialmediafeeds, array('timestamp'));
      $cache_time = $this->getCacheDuration();
      \Drupal::cache()->set($cid, $this->socialmediafeeds, $cache_time);
    }
    if (is_numeric($num_per_page)) {
      return array(
        'data' => array_slice($this->socialmediafeeds, $offset, $num_per_page),
        'total' => count($this->socialmediafeeds),
      );
    }
    return $this->socialmediafeeds;
  }

  //Render fetched data.
  public function posts() {
    $page = pager_find_page();
    $num_per_page = $this->config->get('item_to_show');
    $offset = $num_per_page * $page;
    $offset = $num_per_page * $page;
    $result = $this->getPosts($offset, $num_per_page);
    $total = $result['total'];
    // Now that we have the total number of results, initialize the pager.
    pager_default_initialize($total, $num_per_page);
    $tabs = array(
      'facebook' => 0,
      'twitter' => 0,
      'linkedin' => 0,
      'youtube' => 0,
      'instagram' => 0
    );
    if ($this->config->get('facebook_enable')) {
      $tabs["facebook"] = 1;
    }
    if ($this->config->get('twitter_enable')) {
      $tabs["twitter"] = 1;
    }
    if ($this->config->get('instagram_enable')) {
      $tabs["instagram"] = 1;
    }
    if ($this->config->get('linkedin_enable')) {
      $tabs["linkedin"] = 1;
    }
    if ($this->config->get('youtube_enable')) {
      $tabs["youtube"] = 1;
    }
    // Create a render array with the search results.
    $render = array();
    $render[] = array(
      '#theme' => 'socialhub_page',
      '#content' => array(
        "data" => $result['data'],
        "tabs" => $tabs,
      ),
      '#attached' => array(
        'library' => array(
          'socialmediafeed/socialhub',
          'socialmediafeed/youtube',
        ),
      ),
    );

    // Finally, add the pager to the render array, and return.
    $render[] = array(
      '#type' => 'pager',
      '#element' => 0,
    );
    return $render;
  }

}