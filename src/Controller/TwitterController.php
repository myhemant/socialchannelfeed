<?php

/**
 * @file
 * Contains \Drupal\socialmediafeed\Controller\TwitterController.
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

//use GuzzleHttp\Exception\RequestException;

/**
 * Controller routines for test_api routes.
 */
class TwitterController extends ControllerBase {

    /**
     * Callback for `my-api/get.json` API method.
     */
    protected $apidefault = array();
    protected $twitter_endpoint_uri = "https://api.twitter.com/1.1/statuses/user_timeline.json";
    protected $twitter_oauth_uri = "https://api.twitter.com/oauth2/token";
    protected $config;
    protected $socialmediafeeds = array();

    /**
     * {@inheritdoc}
     */
    public function __construct() {
        $this->config = \Drupal::config('socialmediafeed.config');
    }

    public function getData() {
        $cid = 'socialmediafeed:twitter';

        $data = NULL;
        if ($this->config->get('cache_enable') && $cache = \Drupal::cache()->get($cid)) {
            $data = $cache->data;
            $this->socialmediafeeds = $data;
        } else {
            $this->tweets();
            \Drupal::cache()->set($cid, $this->socialmediafeeds, strtotime('+1 hour'));
        }
        return $this->socialmediafeeds;
    }

    private function tweeter_access_token() {

        $api_url = $this->twitter_oauth_uri;

        $key = $this->config->get('twitter_social_feed_client_id');
        $secret = $this->config->get('twitter_social_feed_client_secret');
        // request token
        $basic_credentials = base64_encode($key . ':' . $secret);

        // build the HTTP GET query
        $body = array(
            'headers' => array(
                "Authorization" => "Basic " . $basic_credentials,
                "Content-type" => "application/x-www-form-urlencoded;charset=UTF-8",
            ),
            'form_params' => array(
                "grant_type" => 'client_credentials',
            ),
            'proxy' => $this->config->get('proxy'),
            'verify' => FALSE,
        );

        try {
            $client = \Drupal::httpClient();
            $response = $client->post($api_url, $body);


            $data = json_decode($response->getBody()->getContents());
            return $data->access_token;
        } catch (RequestException $e) {
            watchdog_exception('socialmediafeed:twitter', $e);
        }
    }

    protected function tweets() {
        $api_url = $this->twitter_endpoint_uri;
        $bearer_token = $this->tweeter_access_token();

        if ($bearer_token) {
            // build the HTTP GET query
            $body = array(
                'headers' => array(
                    "Authorization" => "Bearer " . $bearer_token,
                ),
                'query' => array(
                    "count" => $this->config->get('twitter_item_to_fetch'),
                    "screen_name" => $this->config->get('twitter_social_feed_screen_name'),
                ),
                'proxy' => $this->config->get('proxy'),
                'verify' => FALSE,
            );

            try {
                $client = \Drupal::httpClient();
                $response = $client->get($api_url, $body);
                $twitter_values = json_decode($response->getBody()->getContents(), true);
                $teaser_text = "Read more...";
                $display_time = 1;
                $twitter_hash_tag = 1;
                $twitter_tweets = array();
                foreach ($twitter_values as $key => $twitter_value) {
                    $twitter_tweets[$key]['timestamp'] = strtotime($twitter_value['created_at']);
                    $twitter_tweets[$key]['username'] = $twitter_value['user']['screen_name'];
                    $twitter_tweets[$key]['full_username'] = 'http://twitter.com/' . $twitter_value['user']['screen_name'];
                    preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $twitter_value['text'], $extra_links);
                    foreach ($extra_links[0] as $extra_link) {
                        $twitter_tweets[$key]['extra_links'][] = $extra_link;
                    }
                    if (isset($twitter_value['text'])) {
                        $twitter_tweets[$key]['tweet'] = rtrim($twitter_value['text'], $extra_link);
                    }
                    if (isset($teaser_text) && !empty($teaser_text)) {
                        if (array_key_exists('media', $twitter_value['entities'])) {
                            $url = URL::fromUri($twitter_value['entities']['media'][0]['url'], array('attributes' => array('target' => '_blank')));
                            $twitter_tweets[$key]['tweet_url'] = \Drupal::l(
                                            t('@teaser_text', array('@teaser_text' => $teaser_text)), $url);
                        }
                    }
                    if ($display_time == 1) {
                        $time = strtotime($twitter_value['created_at']);
                        $twitter_tweets[$key]['twitter_date'] = 
                        \Drupal::service('date.formatter')->format($time);
                    }
                    if ($twitter_hash_tag == 1) {
                        $twitter_tweets[$key]['tweet'] = preg_replace_callback(
                                '/#(\\w+)|@(\\w+)/', function ($hash) {
                            if ($hash[0][0] == '#') {
                                $url = URL::fromUri('//twitter.com/hashtag/' . $hash[1], array('attributes' => array('target' => '_blank')));
                                return \Drupal::l($hash[0], $url);
                            }
                            if ($hash[0][0] == '@') {
                                $url = URL::fromUri('//twitter.com/' . $hash[2], array('attributes' => array('target' => '_blank')));
                                return \Drupal::l($hash[0], $url);
                            }
                        }, $twitter_tweets[$key]['tweet']
                        );
                    }
                    $message_feed = array(
                        '#theme' => 'socialhub_twitter',
                        '#twitter' => $twitter_tweets[$key],
                        'timestamp' => $twitter_tweets[$key]['timestamp'],
                    );
                    array_push($this->socialmediafeeds, $message_feed);
                }
            } catch (RequestException $e) {
                watchdog_exception('socialmediafeed:twitter', $e);
            } catch (ConnectException $e) {
                watchdog_exception('socialmediafeed:twitter', $e);
            } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
                watchdog_exception('socialmediafeed:twitter', $e);
            } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
                watchdog_exception('socialmediafeed:twitter', $e);
            } catch (Guzzle\Http\Exception\BadResponseException $e) {
                watchdog_exception('socialmediafeed:twitter', $e);
            } catch (Exception $e) {
                watchdog_exception('socialmediafeed:twitter', $e);
            }
        }
    }

}