<?php

/**
 * @file
 * Contains \Drupal\socialmediafeed\Controller\InstagramController.
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
class InstagramController extends ControllerBase {

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

    public function getData() {
        $cid = 'socialmediafeed:instagram';

        $data = NULL;
        if ($this->config->get('cache_enable') && $cache = \Drupal::cache()->get($cid)) {
            $data = $cache->data;
            $this->socialmediafeeds = $data;
        } else {
            $this->instapost();
            \Drupal::cache()->set($cid, $this->socialmediafeeds, strtotime('+1 hour'));
        }
        return $this->socialmediafeeds;
    }

    protected function instapost() {
        $api_url = "https://api.instagram.com/v1/users/self/media/recent/";

        $access_token = $this->config->get('instagram_social_feed_api_key');
        if ($access_token) {
            // build the HTTP GET query
            $body = array(
                'query' => array(
                    "access_token" => $access_token,
                ),
                'proxy' => $this->config->get('proxy'),
                'verify' => FALSE,
            );

            try {
                $client = \Drupal::httpClient();
                $response = $client->get($api_url, $body);
                $instagram_feed = json_decode($response->getBody()->getContents());
                $insta_posts = array();
                $hash_tag = 1;
                $insta_data = array_slice($instagram_feed->data, 0, $this->config->get('instagram_item_to_fetch'));
                foreach ($insta_data as $feed) {
                    // Return tags as comma delimited string.
                    $tags = implode(',', $feed->tags);
                    $caption = isset($feed->caption->text) ? $feed->caption->text : '';
                    // Rewrite urls to use https.
                    $low_resolution = str_replace('http:', 'https:', $feed->images->low_resolution->url);
                    $thumbnail = str_replace('http:', 'https:', $feed->images->thumbnail->url);
                    $standard_resolution = str_replace('http:', 'https:', $feed->images->standard_resolution->url);
                    $data = array(
                        'feed_id' => $feed->id,
                        'user_id' => $feed->user->id,
                        'tags' => Xss::filter(utf8_encode($tags)),
                        // Time stored in unix epoch format.
                        'created_time' => \Drupal::service('date.formatter')
                        ->format($feed->created_time),
                        'low_resolution' => array(
                            '#theme' => 'image',
                            '#uri' => $low_resolution,
                        ),
                        'thumbnail' => array(
                            '#theme' => 'image',
                            '#uri' => $thumbnail,
                        ),
                        'standard_resolution' => array(
                            '#theme' => 'image',
                            '#uri' => $standard_resolution,
                        ),
                        'caption' => Xss::filter(utf8_encode($caption)),
                        'instagram_id' => $feed->id,
                        'instagram_link' => $feed->link,
                        'instagram_user' => $feed->user->username,
                        'instagram_user_fullname' => $feed->user->full_name,
                            // 'approve' => $feed->auto_publish,
                    );
                    if ($hash_tag == 1) {
                        $data['caption'] = preg_replace_callback(
                                '/#(\\w+)|@(\\w+)/', function ($hash) {
                            if ($hash[0][0] == '#') {
                                $url = URL::fromUri('//www.instagram.com/explore/tags/' . $hash[1], array('attributes' => array('target' => '_blank')));
                                return \Drupal::l($hash[0], $url);
                            }
                            if ($hash[0][0] == '@') {
                                $url = URL::fromUri('//www.instagram.com/' . $hash[2], array('attributes' => array('target' => '_blank')));
                                return \Drupal::l($hash[0], $url);
                            }
                        }, $data['caption']
                        );
                    }
                    $message_feed = array(
                        '#theme' => 'socialhub_instagram',
                        '#instagram' => $data,
                        'timestamp' => $feed->created_time,
                    );
                    array_push($this->socialmediafeeds, $message_feed);
                }
            } catch (RequestException $e) {
                watchdog_exception('socialmediafeed:instagram', $e);
            } catch (ConnectException $e) {
                watchdog_exception('socialmediafeed:instagram', $e);
            } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
                watchdog_exception('socialmediafeed:instagram', $e);
            } catch (Guzzle\Http\Exception\ServerErrorResponseException $e) {
                watchdog_exception('socialmediafeed:instagram', $e);
            } catch (Guzzle\Http\Exception\BadResponseException $e) {
                watchdog_exception('socialmediafeed:instagram', $e);
            } catch (Exception $e) {
                watchdog_exception('socialmediafeed:instagram', $e);
            }
        } else {
            $message_feed = array(
                '#theme' => 'socialhub_instagram',
                '#instagram' => null,
                'timestamp' => time(),
            );
            array_push($this->socialmediafeeds, $message_feed);
        }
    }

}