<?php

/**
 * @file
 * Contains \Drupal\socialmediafeed\Controller\YoutubeController.
 */

namespace Drupal\socialmediafeed\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\socialmediafeed\Controller\SocialMediaFeedController;

/**
 * Controller routines for test_api routes.
 */
class YoutubeController extends ControllerBase {

  /**
   * Callback for `my-api/get.json` API method.
   */
  protected $apidefault = array();
  protected $youtube_endpoint_uri = "https://www.googleapis.com/youtube/v3/search";
  protected $config;
  protected $socialmediafeeds = array();

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->config = \Drupal::config('socialmediafeed.config');
  }

  public function getData() {
    $cid = 'socialmediafeed:youtube';

    $data = NULL;
    if ($this->config->get('cache_enable') && $cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
      $this->socialmediafeeds = $data;
    }
    else {
      $this->youtube_videos();
      \Drupal::cache()->set($cid, $this->socialmediafeeds, strtotime('+1 hour'));
    }
    return $this->socialmediafeeds;
  }

  private function youtube_videos() {
    $api_url = $this->youtube_endpoint_uri;
    $this->youtube_refresh_token();
    $bearer_token = $this->config->get('youtube_social_feed_api_key');

    $bearer_token = $this->config->get('youtube_social_feed_api_key');

    if ($bearer_token) {
      // build the HTTP GET query
      $body = array(
        'headers' => array(
          "Authorization" => "Bearer " . $bearer_token,
        ),
        'query' => array(
          "forMine" => "true", //$this->config->get('youtube_social_feed_page_id'),
          "part" => "snippet,id",
          "order" => "date",
          "maxResults" => $this->config->get('youtube_item_to_fetch'),
          "type" => 'video',
        ),
        'proxy' => $this->config->get('proxy'),
        'verify' => FALSE,
      );
      try {
        $client = \Drupal::httpClient();
        $response = $client->get($api_url, $body);
        $data = json_decode($response->getBody()->getContents(), true);
        foreach ($data['items'] as $yoututbe) {
          $video = array(
            'videoid' => $yoututbe['id']['videoId'],
            'title' => $yoututbe['snippet']['title'],
            'description' => $yoututbe['snippet']['description'],
            // Time stored in unix epoch format.
            'publishedAt' => \Drupal::service('date.formatter')
              ->format(strtotime($yoututbe['snippet']['publishedAt'])),
            'thumbnail' => array(
              '#theme' => 'image',
              '#uri' => $yoututbe['snippet']['thumbnails']['default']['url'],
            ),
            'medium' => array(
              '#theme' => 'image',
              '#uri' => $yoututbe['snippet']['thumbnails']['medium']['url'],
            ),
            'high' => array(
              '#theme' => 'image',
              '#uri' => $yoututbe['snippet']['thumbnails']['high']['url'],
            ),
          );
          $message_feed = array(
            '#theme' => 'socialhub_youtube',
            '#youtube' => $video,
            'timestamp' => strtotime($video['publishedAt']),
          );
          array_push($this->socialmediafeeds, $message_feed);
        }
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
    }
  }

  //Function to refresh youtube access token
  private function youtube_refresh_token() {
    $config = $this->config;
    $refresh_token = $config->get('youtube_social_feed_refreshToken');
    $expire_in = $config->get('youtube_social_feed_expire_in');
    if ($expire_in < time()) {
      // build the HTTP GET query
      $body = array(
        'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
        'form_params' => array(
          "client_id" => $config->get('youtube_social_feed_client_id'),
          "client_secret" => $config->get('youtube_social_feed_client_secret'),
          "grant_type" => "refresh_token",
          "refresh_token" => $refresh_token,
        ),
        'verify' => FALSE,
        'proxy' => $config->get('proxy'),
      );

      try {
        $client = \Drupal::httpClient();
        $response = $client->post("https://accounts.google.com/o/oauth2/token", $body);
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
            ->set('youtube_social_feed_expire_in', (time() + $auth->expires_in))
            ->save();
        $access_key = $auth->access_token;
      }
      else {
        drupal_set_message($auth->error_message, 'error');
      }
    }
  }

}