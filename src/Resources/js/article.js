import {configReader} from "./config-reader";

'use strict';

const { Blog } = global;

$(function() {
  const initialized = { '#vk-comments': false, '#disqus_thread': false };

  const initComments = function(type) {
    if (initialized[type]) { return; }

    switch (type) {
      case '#disqus_thread':
        // Disqus API needs this variable. So please don't warn of unused variable
        window.disqus_config = function() {
          this.page.url = configReader.get('article_url');
          return this.page.identifier = configReader.get('page_identifier');
        };

        (function() {
          const d = document;
          const s = d.createElement('script');
          s.src = `//${configReader.get('discuss_user_name')}.disqus.com/embed.js`;
          s.setAttribute('data-timestamp', +new Date());
          return (d.head || d.body).appendChild(s);
        })();
        break;
      case '#vk-comments':
        if (typeof VK === 'undefined') { return; }

        VK.Widgets.Comments(
          "vk-comments",
          { limit: 5, attach: "*", pageUrl: configReader.get('article_url') },
          configReader.get('article_original_id')
        );
        break;
      default: return;
    }

    return initialized[type] = true;
  };

  const $tabToggler = $('.comments-wrapper a[data-toggle="tab"]');
  $tabToggler.on('shown.bs.tab', e => initComments($(e.target).attr("href")));

  return initComments($tabToggler.closest('.active').find('a[data-toggle="tab"]').attr('href'));
});