<?php

declare(strict_types=1);

use TheFrosty\WpBlockAiScrapers\Api\File;
use TheFrosty\WpBlockAiScrapers\Api\Htaccess;
use TheFrosty\WpBlockAiScrapers\Api\RobotsTxt;
use TheFrosty\WpBlockAiScrapers\WpAdmin\Settings;

$query ??= '';
$class ??= 'hidden';

$showLinks = [
    sprintf(
        '<a href="%1$s" data-file="%4$s" aria-label="%2$s" title="%2$s">%3$s</a>',
        Htaccess::getActionUrl(File::_HTACCESS),
        esc_attr__('Show the .htaccess code', 'wp-block-ai-scrapers'),
        esc_html__('.htaccess', 'wp-block-ai-scrapers'),
        esc_attr(File::_HTACCESS->value)
    ),
    sprintf(
        '<a href="%1$s" data-file="%4$s" aria-label="%2$s" title="%2$s">%3$s</a>',
        RobotsTxt::getActionUrl(File::NGINX),
        esc_attr__('Show the Nginx code', 'wp-block-ai-scrapers'),
        esc_html__('Nginx', 'wp-block-ai-scrapers'),
        esc_attr(File::NGINX->value)
    ),
    sprintf(
        '<a href="%1$s" data-file="%4$s" aria-label="%2$s" title="%2$s">%3$s</a>',
        RobotsTxt::getActionUrl(File::ROBOTS_TXT),
        esc_attr__('Show the robots.txt code', 'wp-block-ai-scrapers'),
        esc_html__('robots.txt', 'wp-block-ai-scrapers'),
        esc_attr(File::ROBOTS_TXT->value)
    ),
];

$writeLinks = [
    sprintf(
        '<a href="%1$s" onclick="return confirm(\'%2$s?\');" aria-label="%2$s" title="%2$s">%3$s</a>',
        Htaccess::getActionUrl(File::_HTACCESS),
        esc_attr__('Write to the .htaccess file', 'wp-block-ai-scrapers'),
        esc_html__('.htaccess', 'wp-block-ai-scrapers'),
    ),
    sprintf(
        '<a href="%1$s" onclick="return confirm(\'%2$s?\');" aria-label="%2$s" title="%2$s">%3$s</a>',
        RobotsTxt::getActionUrl(File::ROBOTS_TXT),
        esc_attr__('Write to the robots.txt file', 'wp-block-ai-scrapers'),
        esc_html__('robots.txt', 'wp-block-ai-scrapers'),
    ),
];

?>
<script defer>
  document.addEventListener('DOMContentLoaded', () => {
      const element = document.getElementById('block-ai-scrapers-settings')
      const img = document.getElementById('block-ai-spinner')
      const links = document.querySelectorAll('a[data-file]')
      links.forEach((link) => {
        link.addEventListener('click', function (e) {
            element.style.width = `${element.parentElement.offsetWidth}px`
            img.classList.remove('hidden')
            const request = wp.ajax.post({
              action: '<?php echo Settings::ACTION_RETRIEVE; ?>',
              file: this.dataset.file.toString(),
              nonce: '<?php echo wp_create_nonce(Settings::ACTION_RETRIEVE); ?>'
            })

            request.done(function (response) {
              if (response) {
                element.replaceChildren()
                element.innerHTML = `<pre style="padding:7px;overflow:auto;border:1px solid #c3c4c7;">${response}</pre>`
                element.classList.remove('hidden')
                img.classList.add('hidden')
              }
            })

            e.preventDefault()
          }, { passive: false }
        )
      })
    }
  )
</script>
<tr id="block-ai-scrapers" class="inactive <?php
echo $class; ?>">
    <th><img id="block-ai-spinner" src="<?php
        echo admin_url('/images/spinner-2x.gif'); ?>" alt="" class="hidden" loading="lazy" width="20px"></th>
    <td class="plugin-title column-primary">
        <strong><?php
            esc_html_e('Show code', 'wp-block-ai-scrapers'); ?></strong>
        <div class="row-actions visible">
            <?php
            foreach ($showLinks as $key => $link) {
                printf('<span>%s%s</span>', $link, $key === array_key_last($showLinks) ? '' : ' | ');
            } ?>
        </div>

        <strong><?php
            esc_html_e('Write code to file', 'wp-block-ai-scrapers'); ?></strong>
        <div class="row-actions visible">
            <?php
            foreach ($writeLinks as $key => $link) {
                printf('<span>%s%s</span>', $link, $key === array_key_last($writeLinks) ? '' : ' | ');
            } ?>
        </div>
    </td>
    <td colspan="2">
        <div id="block-ai-scrapers-settings" class="hidden"></div>
    </td>
</tr>
