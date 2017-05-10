/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

    /* Light YouTube Embeds by @labnol */
    /* Web: http://labnol.org/?p=27941 */

   // external js: isotope.pkgd.js
(function ($) {
// init Isotope
$(document).ready(function(){
  var $grid = $('.grid').isotope({
    itemSelector: '.grid-item',
    masonry: {
      columnWidth: '.shuffle_sizer'
    }
  });
  // necessary to apply masonry to new items pulled in from infinite_scroll.js
  $grid.bind('change', function() {
    $grid.isotope('reloadItems');
      $grid.isotope();
    });

    $('.grid img').on('load', function(){
      $grid.isotope('reloadItems');
      $grid.isotope();
    });

  // bind filter button click
  $('.filters-button-group').on( 'click', 'a, button', function() {
    var filterValue = $( this ).attr('data-group');
    $grid.isotope({ filter: filterValue });
    return false;
  });
  // change is-checked class on buttons
  $('.filters-button-group').each( function( i, buttonGroup ) {
    var $buttonGroup = $( buttonGroup );
    $buttonGroup.on( 'click', 'a, button', function() {
      $buttonGroup.find('.active').removeClass('active');
      $( this ).addClass('active');
    });
  });
  if($('.pager__item--next a').length <= 0 ) {
    $('#social_hub_loadmore').hide();
  }
  // Make sure that autopager plugin is loaded
  if($.autopager) {
    // define autopager parameters
    var content_selector = '.grid';
    var items_selector = content_selector + ' .shuffle-item';
    var next_selector = '.pager__item--next a';
    var url = $('.pager__item--last a').attr('href');
    var last_page = url.substring(url.indexOf("=")+1);
    var pager_selector = '.pager-nav';
    $(pager_selector).hide();
    var page = 1;
    var handle = $.autopager({
      autoLoad: false,
      appendTo: content_selector,
      content: items_selector,
      link: next_selector,
      load: function() {
        $(content_selector).trigger('change');
        $('#social_hub_content img').on('load', function(){
          $grid.isotope('reloadItems');
          $grid.isotope();
        });
        $('#social_hub_loadmore').text('Load more');
        
        if(page++ == last_page) {
           $('#social_hub_loadmore').hide();
        }
        else {
          $('#social_hub_loadmore').attr('disabled', false);
        }
        
      }
    });
    
    $('#social_hub_loadmore').click(function(){
      $(this).text('Loading...');
      $(this).attr('disabled', true);
      handle.autopager('load');
    })
  }
});

}(jQuery));