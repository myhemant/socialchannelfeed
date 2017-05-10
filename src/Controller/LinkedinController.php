<?php

/**
 * @file
 * Contains \Drupal\socialmediafeed\Controller\LinkedinController.
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
class LinkedinController extends ControllerBase {

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
        $cid = 'socialmediafeed:linkedin';

        $data = NULL;
        if ($this->config->get('cache_enable') && $cache = \Drupal::cache()->get($cid)) {
            $data = $cache->data;
            $this->socialmediafeeds = $data;
        } else {
            $this->linkedinpost();
            \Drupal::cache()->set($cid, $this->socialmediafeeds, strtotime('+1 hour'));
        }
        return $this->socialmediafeeds;
    }

    private function linkedinpost() {
        $api_url = "https://api.linkedin.com/v1/companies/" .
                $this->config->get('linkedin_social_feed_page_id') . "/updates";
        $bearer_token = $this->config->get('linkedin_social_feed_api_key');
        // build the HTTP GET query
        if ($bearer_token) {
            $body = array(
                'headers' => array(
                    "Authorization" => "Bearer " . $bearer_token,
                ),
                'query' => array(
                    "format" => 'json',
                    "count" => $this->config->get('linkedin_item_to_fetch'),
                ),
                'proxy' => $this->config->get('proxy'),
                'verify' => FALSE,
            );

            try {
                $client = \Drupal::httpClient();
                $response = $client->get($api_url, $body);
                $linkedin_feed = json_decode($response->getBody()->getContents(), TRUE);
                foreach ($linkedin_feed['values'] as $key => $linkedin_value) {
                    $value = $linkedin_value['updateContent']['companyStatusUpdate']['share'];
                    $data = array(
                        'description' => $value['comment'],
                        'source' => "https://www.linkedin.com/hp/update/" . $value['id'],
                        'date' => format\Drupal::service('date.formatter')
                        ->format($linkedin_value['timestamp']),
                    );
                    if (isset($value['content']['submittedImageUrl'])) {
                        $data['full_picture'] = array(
                            '#theme' => 'image',
                            '#uri' => $value['content']['submittedImageUrl'],
                        );
                        $data['thumbnail'] = array(
                            '#theme' => 'image',
                            '#uri' => $value['content']['thumbnailUrl'],
                        );
                    }
                    $message_feed = array(
                        '#theme' => 'socialhub_linkedin',
                        '#linkedin' => $data,
                        'timestamp' => $linkedin_value['timestamp'],
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
        }
    }

}