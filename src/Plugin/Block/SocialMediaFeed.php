<?php

namespace Drupal\socialmediafeed\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\socialmediafeed\Controller\SocialMediaFeedController;

/**
 * Provides a 'Social Media Feed' Block.
 *
 * @Block(
 *   id = "socialmediafeed_block",
 *   admin_label = @Translation("Social Media Feed Block"),
 * )
 */
class SocialMediaFeed extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $getPosts = new SocialMediaFeedController();
    return $getPosts->posts();
  }

}