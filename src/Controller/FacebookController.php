<?php

/**
 * @file
 * Contains \Drupal\socialmediafeed\Controller\FacebookController.
 */

namespace Drupal\socialmediafeed\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\BadResponseException;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\socialmediafeed\Controller\SocialMediaFeedController;

//use GuzzleHttp\Exception\RequestException;

/**
 * Controller routines for test_api routes.
 */
class FacebookController extends ControllerBase {

  /**
   * Callback for `my-api/get.json` API method.
   */
  protected $apidefault = array();
  protected $fb_endpoint_uri = "https://graph.facebook.com/v2.8/";
  protected $fb_oauth_uri = "https://graph.facebook.com/oauth/access_token";
  protected $config;
  protected $socialmediafeeds = array();

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->config = \Drupal::config('socialmediafeed.config');
  }

  public function getData() {
    $cid = 'socialmediafeed:facebook';

    $data = NULL;
    if ($this->config->get('cache_enable') && $cache = \Drupal::cache()->get($cid)) {
      $data = $cache->data;
      $this->socialmediafeeds = $data;
    }
    else {
      $this->fbposts();
      \Drupal::cache()->set($cid, $this->socialmediafeeds, strtotime('+1 hour'));
    }
    return $this->socialmediafeeds;
  }

  function fb_access_token() {
    $api_url = $this->fb_oauth_uri;
    $access_token = FALSE;
    // build the HTTP GET query
    $body = array(
      //'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
      'query' => array(
        "grant_type" => "client_credentials",
        "client_id" => $this->config->get('fb_social_feed_client_id'),
        "client_secret" => $this->config->get('fb_social_feed_client_secret'),
      ),
      'proxy' => $this->config->get('proxy'),
      'verify' => FALSE,
    );

    try {
      $client = \Drupal::httpClient();
      $response = $client->get($api_url, $body);
      $status = $response->getStatusCode();
      if ($status == 200) {
        $data = json_decode($response->getBody()->getContents());
        $access_token = $data->access_token;
        //$config->set('access_token', $access_token)->save();
        return $access_token;
      }
    } catch (RequestException $e) {
      watchdog_exception('socialmediafeed:facebook', $e);
    } catch (ConnectException $e) {
      watchdog_exception('socialmediafeed:facebook', $e);
    } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
      watchdog_exception('socialmediafeed:facebook', $e);
    } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
      watchdog_exception('socialmediafeed:facebook', $e);
    } catch (Guzzle\Http\Exception\BadResponseException $e) {
      watchdog_exception('socialmediafeed:facebook', $e);
    } catch (Exception $e) {
      watchdog_exception('socialmediafeed:facebook', $e);
    }
    return $access_token;
  }

  protected function fbposts() {

    $api_url = $this->fb_endpoint_uri . "/" .
        $this->config->get('fb_page_id') . "/posts";

    // build the HTTP GET query
    $access_token = $this->fb_access_token();
    if ($access_token) {
      $body = array(
        //'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
        'query' => array(
          "access_token" => $access_token,
          "fields" => 'id,created_time,message,permalink_url,
            full_picture,source,properties,object_id',
          "limit" => $this->config->get('fb_item_to_fetch'),
        ),
        'proxy' => $this->config->get('proxy'),
        'verify' => FALSE,
      );

      try {
        $client = \Drupal::httpClient();
        $response = $client->get($api_url, $body);
        $json_response = json_decode($response->getBody()->getContents(), true);
        $message_feed = array();
        if (isset($json_response['data']) && !empty($json_response['data'])) {
          $i = 0;
          foreach ($json_response['data'] as $facebook_value) {
            // If all post type selected.
            $message_feed = $this->facebook_processed_data($i, $facebook_value, 0, 1, 1, 1, 1, 0);

            array_push($this->socialmediafeeds, $message_feed);
            $i++;
          }
        }
      } catch (RequestException $e) {
        watchdog_exception('socialmediafeed:facebook', $e);
      } catch (ConnectException $e) {
        watchdog_exception('socialmediafeed:facebook', $e);
      } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
        watchdog_exception('socialmediafeed:facebook', $e);
      } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
        watchdog_exception('socialmediafeed:facebook', $e);
      } catch (Guzzle\Http\Exception\BadResponseException $e) {
        watchdog_exception('socialmediafeed:facebook', $e);
      } catch (Exception $e) {
        watchdog_exception('socialmediafeed:facebook', $e);
      }
    }
  }

  /**
   * Rendering values from the Facebook feed.
   */
  protected function facebook_processed_data($i, $facebook_entry, $display_all_posts, $display_time, $display_pic, $display_video, $teaser_text, $facebook_hash_tag) {
    $trim_length = $this->config->get('fb_des_trim');
    $facebook_hash_tag = 1;
    $teaser_text = 'Read more...';
    $message_feed['timestamp'] = strtotime($facebook_entry['created_time']);
    if (array_key_exists('message', $facebook_entry)) {

      if ($display_video == 1 && isset($facebook_entry['source'])) {
        $display_pic = 0;
        if (isset($facebook_entry['object_id'])) {
          $message_feed['video'] = $facebook_entry['source'];

          $api_url = $this->fb_endpoint_uri . "/" .
              $facebook_entry['object_id'];

          // build the HTTP GET query
          $access_token = $this->fb_access_token();

          $body = array(
            //'headers' => array("Content-type" => "application/x-www-form-urlencoded"),
            'query' => array(
              "access_token" => $access_token,
              "fields" => 'embed_html',
              "limit" => 20,
            ),
            'proxy' => $this->config->get('proxy'),
            'verify' => FALSE,
          );

          try {
            $client = \Drupal::httpClient();
            $response = $client->get($api_url, $body);
            $video = json_decode($response->getBody()->getContents(), true);
            if (isset($video['embed_html']) && !empty($video['embed_html'])) {
              $message_feed['video'] = $video['embed_html'];
            }
          } catch (RequestException $e) {
            watchdog_exception('socialmediafeed:facebook', $e);
          } catch (ConnectException $e) {
            watchdog_exception('socialmediafeed:facebook', $e);
          } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
            watchdog_exception('socialmediafeed:facebook', $e);
          } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
            watchdog_exception('socialmediafeed:facebook', $e);
          } catch (Guzzle\Http\Exception\BadResponseException $e) {
            watchdog_exception('socialmediafeed:facebook', $e);
          } catch (Exception $e) {
            watchdog_exception('socialmediafeed:facebook', $e);
          }
        }
        else if (strpos($facebook_entry['source'], "youtu") !== false) {
          preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $facebook_entry['source'], $matches);
          $message_feed['video'] = '<div class="youtube-player" data-id="' . $matches[1] . '"></div>';
        }
        else {
          $message_feed['video'] = '<video width="100%" controls>
            <source src="' . $facebook_entry['source'] . '" type="video/mp4" ge-video>
            Your browser does not support HTML5 video.
          </video>';
        }
      }
      if ($display_pic == 1 && isset($facebook_entry['full_picture'])) {
        $message_feed['full_picture'] = array(
          '#theme' => 'image',
          '#uri' => $facebook_entry['full_picture'],
        );
      }

      if (isset($facebook_entry['message'])) {
        if (isset($trim_length) && !empty($trim_length)) {
          $matches = "";
          $trimmed_message = substr($facebook_entry['message'], 0, $trim_length);
          $regex = "(.*)\b.+";
          if (function_exists('mb_ereg')) {
            mb_regex_encoding('UTF-8');
            $found = mb_ereg($regex, $trimmed_message, $matches);
          }
          else {
            $found = preg_match("/$regex/us", $trimmed_message, $matches);
          }
          if ($found) {
            $trimmed_message = $matches[1];
          }
          $message_feed['message'] = $trimmed_message;
        }
        else {
          $message_feed['message'] = substr($facebook_entry['message'], 0, 200);
        }
        $url = '@(http)?(s)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@';
         $message_feed['message'] = preg_replace($url, 
             '<a href="http$2://$4" title="$0">$0</a>',  
             $message_feed['message']);
      }
      if (isset($teaser_text) && !empty($teaser_text) && isset($facebook_entry['permalink_url'])) {
        $url = Url::fromUri($facebook_entry['permalink_url'], array('attributes' => array('target' => '_blank')));
        $message_feed['full_feed_link'] = \Drupal::l(
                t('@teaser_text', array('@teaser_text' => $teaser_text)), $url);
      }
      else {
        $message_feed['full_feed_link'] = t('@teaser_text', array('@teaser_text' => $teaser_text));
      }

      if ($facebook_hash_tag == 1) {
        $message_feed['message'] = preg_replace_callback(
            '/#(\\w+)/', function ($hash) {
              $url = Url::fromUri('https:facebook.com/hashtag/' . $hash[1], array('attributes' => array('target' => '_blank')));
              return \Drupal::l($hash[0], $url);
            }, $message_feed['message']
        );
      }
      if ($display_time == 1) {
        $time = strtotime($facebook_entry['created_time']);
        $message_feed['created_stamp'] = \Drupal::service('date.formatter')
            ->format($time);
      }
    }
    else {
      if ($display_pic == 1 && isset($facebook_entry['full_picture'])) {
        $message_feed['full_picture'] = array(
          '#theme' => 'image',
          '#uri' => $facebook_entry['full_picture'],
        );
      }
      if ($display_video == 1 && isset($facebook_entry['source'])) {
        $message_feed['video'] = '<video width="100%" controls>
            <source src="' . $facebook_entry['source'] . '" type="video/mp4" ge-video>
            Your browser does not support HTML5 video.
          </video>';
      }
      if (isset($teaser_text) && !empty($teaser_text) && isset($facebook_entry['permalink_url'])) {
        $url = URL::fromUri($facebook_entry['permalink_url']);
        $message_feed['full_feed_link'] = \Drupal::l(
                t('@teaser_text', array('@teaser_text' => $teaser_text)), $url, array('attributes' => array('target' => '_blank')));
      }
      else {
        $message_feed['full_feed_link'] = t('@teaser_text', array('@teaser_text' => $teaser_text));
      }
    }
    $feed = array(
      '#theme' => 'socialhub_facebook',
      '#facebook' => $message_feed,
      'timestamp' => $message_feed['timestamp'],
    );
    return $feed;
  }

}