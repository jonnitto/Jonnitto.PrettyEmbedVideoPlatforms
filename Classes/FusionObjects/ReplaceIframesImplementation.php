<?php

namespace Jonnitto\PrettyEmbedVideoPlatforms\FusionObjects;

use Jonnitto\PrettyEmbedHelper\Service\ParseIDService;
use Jonnitto\PrettyEmbedHelper\Service\YoutubeService;
use Jonnitto\PrettyEmbedHelper\Utility\Utility;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use function strpos;
use function preg_match_all;
use function str_replace;

/**
 * Replace embeded iframes with the video
 */
class ReplaceIframesImplementation extends AbstractFusionObject
{
    /**
     * @Flow\Inject
     * @var YoutubeService
     */
    protected $youtubeService;

    /**
     * @return string
     */
    public function getContent(): string
    {
        return (string) $this->fusionValue('content');
    }

    /**
     * Render Replace embeded iframes with the pretty embeded markup
     *
     * @return string
     */
    public function evaluate(): string
    {
        $content = $this->getContent();

        if (!strpos($content, '<iframe') && (!strpos($content, 'https://www.youtube.com/embed') || !strpos($content, 'https://www.youtube-nocookie.com/embed') || !strpos($content, 'https://player.vimeo.com/video'))) {
            return $content;
        }

        preg_match_all('/<iframe[^>]*?src=\"(https:\/\/www\.youtube(?:-nocookie)?\.com\/embed\/[^"]*)(?:(?!<\/iframe>).)+<\/iframe>/im', $content, $youtubeIframeMatcher, PREG_SET_ORDER);
        preg_match_all('/<iframe[^>]*?src=\"(https:\/\/player\.vimeo\.com\/video\/[^"]*)(?:(?!<\/iframe>).)+<\/iframe>/im', $content, $vimeoIframeMatcher, PREG_SET_ORDER);
        $parseID = new ParseIDService();

        foreach ($youtubeIframeMatcher as $array) {
            $iframe = $array[0] ?? null;
            $url = $array[1] ?? null;
            if (!$iframe || !$url) {
                continue;
            }
            $videoID = $parseID->youtube($url);
            if (!$videoID) {
                continue;
            }
            $type = $this->youtubeService->type($url);
            $replacement = $this->buildYoutube($videoID, $type);
            $content = str_replace($iframe, $replacement, $content);
        }

        foreach ($vimeoIframeMatcher as $array) {
            $iframe = $array[0] ?? null;
            $url = $array[1] ?? null;
            if (!$iframe || !$url) {
                continue;
            }
            $videoID = $parseID->vimeo($url);
            if (!$videoID) {
                continue;
            }
            $replacement = $this->buildVimeo($videoID);
            $content = str_replace($iframe, $replacement, $content);
        }

        return $content;
    }

    protected function buildYoutube(string $videoID, string $type): string
    {
        $poster = Utility::youtubeThumbnail($videoID);
        $href = Utility::youtubeHref($videoID, $type, false);
        $embedHref = Utility::youtubeHref($videoID, $type, true);
        $this->runtime->pushContextArray([
            'videoID' => $videoID,
            'type' => $type,
            'poster' => $poster,
            'href' => $href,
            'embedHref' => $embedHref,
        ]);
        $html = $this->runtime->render($this->path . '/itemYoutubeRenderer');
        $this->runtime->popContext();
        return $html ?? '';
    }

    protected function buildVimeo(string $videoID): string
    {
        $poster = Utility::vimeoThumbnail($videoID);
        $href = Utility::vimeoHref($videoID, false);
        $embedHref = Utility::vimeoHref($videoID, true);
        $this->runtime->pushContextArray([
            'videoID' => $videoID,
            'poster' => $poster,
            'href' => $href,
            'embedHref' => $embedHref,
        ]);
        $html = $this->runtime->render($this->path . '/itemVimeoRenderer');
        $this->runtime->popContext();
        return $html ?? '';
    }
}
