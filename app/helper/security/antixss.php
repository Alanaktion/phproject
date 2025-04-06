<?php

declare(strict_types=1);

namespace Helper\Security;

use const ENT_DISALLOWED;
use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const HTML_ENTITIES;

/**
 * Partial port of Voku/AntiXSS, originally ported from CodeIgniter
 *
 * Only includes the functionality Phproject directly relies on.
 *
 * @copyright Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @copyright Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @copyright Copyright (c) 2015 - 2020, Lars Moelleken (https://moelleken.org/)
 * @license   http://opensource.org/licenses/MIT MIT License
 */
final class AntiXSS
{
    private const VOKU_ANTI_XSS_GT = 'voku::anti-xss::gt';
    private const VOKU_ANTI_XSS_LT = 'voku::anti-xss::lt';

    /**
     * List of never allowed regex replacements.
     *
     * @var string[]
     */
    private $_never_allowed_regex = [
        // default javascript
        '(\(?:?document\)?|\(?:?window\)?(?:\.document)?)\.(?:location|on\w*)' => '',
        // data-attribute + base64
        "([\"'])?data\s*:\s*(?!image\s*\/\s*(?!svg.*?))[^\1]*?base64[^\1]*?,[^\1]*?\1?" => '',
        // old IE, old Netscape
        'expression\s*(?:\(|&\#40;)' => '',
        // src="js"
        'src\=(?<wrapper>[\'|"]).*\.js(?:\g{wrapper})' => '',
        // comments
        '<!--(.*)-->' => '&lt;!--$1--&gt;',
        '<!--'        => '&lt;!--',
    ];

    /**
     * List of html tags that will not close automatically.
     *
     * @var string[]
     */
    private $_do_not_close_html_tags = [];

    /**
     * List of never allowed call statements.
     *
     * @var string[]
     */
    private $_never_allowed_js_callback_regex = [
        '\(?window\)?\.',
        '\(?history\)?\.',
        '\(?location\)?\.',
        '\(?document\)?\.',
        '\(?cookie\)?\.',
        '\(?ScriptElement\)?\.',
        'd\s*a\s*t\s*a\s*:',
    ];

    /**
     * List of simple never allowed call statements.
     *
     * @var string[]
     */
    private $_never_allowed_call_strings = [
        // default javascript
        'javascript',
        // Java: jar-protocol is an XSS hazard
        'jar',
        // Mac (will not run the script, but open it in AppleScript Editor)
        'applescript',
        // IE: https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#VBscript_in_an_image
        'vbscript',
        'vbs',
        // IE, surprise!
        'wscript',
        // IE
        'jscript',
        // https://html5sec.org/#behavior
        'behavior',
        // old Netscape
        'mocha',
        // old Netscape
        'livescript',
        // default view source
        'view-source',
    ];

    /**
     * @var string[]
     */
    private $_never_allowed_str_afterwards = [
        '&lt;script&gt;',
        '&lt;/script&gt;',
    ];

    /**
     * List of never allowed strings, afterwards.
     *
     * @var string[]
     */
    private $_never_allowed_on_events_afterwards = [
        'onAbort',
        'onActivate',
        'onAttribute',
        'onAfterPrint',
        'onAfterScriptExecute',
        'onAfterUpdate',
        'onAnimationCancel',
        'onAnimationEnd',
        'onAnimationIteration',
        'onAnimationStart',
        'onAriaRequest',
        'onAutoComplete',
        'onAutoCompleteError',
        'onAuxClick',
        'onBeforeActivate',
        'onBeforeCopy',
        'onBeforeCut',
        'onBeforeInput',
        'onBeforePrint',
        'onBeforeDeactivate',
        'onBeforeEditFocus',
        'onBeforePaste',
        'onBeforePrint',
        'onBeforeScriptExecute',
        'onBeforeToggle',
        'onBeforeUnload',
        'onBeforeUpdate',
        'onBegin',
        'onBlur',
        'onBounce',
        'onCancel',
        'onCanPlay',
        'onCanPlayThrough',
        'onCellChange',
        'onChange',
        'onClick',
        'onClose',
        'onCommand',
        'onCompassNeedsCalibration',
        'onContextMenu',
        'onControlSelect',
        'onCopy',
        'onCueChange',
        'onCut',
        'onDataAvailable',
        'onDataSetChanged',
        'onDataSetComplete',
        'onDblClick',
        'onDeactivate',
        'onDeviceLight',
        'onDeviceMotion',
        'onDeviceOrientation',
        'onDeviceProximity',
        'onDrag',
        'onDragDrop',
        'onDragEnd',
        'onDragExit',
        'onDragEnter',
        'onDragLeave',
        'onDragOver',
        'onDragStart',
        'onDrop',
        'onDurationChange',
        'onEmptied',
        'onEnd',
        'onEnded',
        'onError',
        'onErrorUpdate',
        'onExit',
        'onFilterChange',
        'onFinish',
        'onFocus',
        'onFocusIn',
        'onFocusOut',
        'onFormChange',
        'onFormInput',
        'onFullScreenChange',
        'onFullScreenError',
        'onGotPointerCapture',
        'onHashChange',
        'onHelp',
        'onInput',
        'onInvalid',
        'onKeyDown',
        'onKeyPress',
        'onKeyUp',
        'onLanguageChange',
        'onLayoutComplete',
        'onLoad',
        'onLoadEnd',
        'onLoadedData',
        'onLoadedMetaData',
        'onLoadStart',
        'onLoseCapture',
        'onLostPointerCapture',
        'onMediaComplete',
        'onMediaError',
        'onMessage',
        'onMouseDown',
        'onMouseEnter',
        'onMouseLeave',
        'onMouseMove',
        'onMouseOut',
        'onMouseOver',
        'onMouseUp',
        'onMouseWheel',
        'onMove',
        'onMoveEnd',
        'onMoveStart',
        'onMozFullScreenChange',
        'onMozFullScreenError',
        'onMozPointerLockChange',
        'onMozPointerLockError',
        'onMsContentZoom',
        'onMsFullScreenChange',
        'onMsFullScreenError',
        'onMsGestureChange',
        'onMsGestureDoubleTap',
        'onMsGestureEnd',
        'onMsGestureHold',
        'onMsGestureStart',
        'onMsGestureTap',
        'onMsGotPointerCapture',
        'onMsInertiaStart',
        'onMsLostPointerCapture',
        'onMsManipulationStateChanged',
        'onMsPointerCancel',
        'onMsPointerDown',
        'onMsPointerEnter',
        'onMsPointerLeave',
        'onMsPointerMove',
        'onMsPointerOut',
        'onMsPointerOver',
        'onMsPointerUp',
        'onMsSiteModeJumpListItemRemoved',
        'onMsThumbnailClick',
        'onOffline',
        'onOnline',
        'onOutOfSync',
        'onPage',
        'onPageHide',
        'onPageShow',
        'onPaste',
        'onPause',
        'onPlay',
        'onPlaying',
        'onPointerCancel',
        'onPointerDown',
        'onPointerEnter',
        'onPointerLeave',
        'onPointerLockChange',
        'onPointerLockError',
        'onPointerMove',
        'onPointerOut',
        'onPointerOver',
        'onPointerRawUpdate',
        'onPointerUp',
        'onPopState',
        'onProgress',
        'onPropertyChange',
        'onqt_error',
        'onRateChange',
        'onReadyStateChange',
        'onReceived',
        'onRepeat',
        'onReset',
        'onResize',
        'onResizeEnd',
        'onResizeStart',
        'onResume',
        'onReverse',
        'onRowDelete',
        'onRowEnter',
        'onRowExit',
        'onRowInserted',
        'onRowsDelete',
        'onRowsEnter',
        'onRowsExit',
        'onRowsInserted',
        'onScroll',
        'onSearch',
        'onSeek',
        'onSeeked',
        'onSeeking',
        'onSelect',
        'onSelectionChange',
        'onSelectStart',
        'onStalled',
        'onStorage',
        'onStorageCommit',
        'onStart',
        'onStop',
        'onShow',
        'onSyncRestored',
        'onSubmit',
        'onSuspend',
        'onSynchRestored',
        'onTimeError',
        'onTimeUpdate',
        'onTimer',
        'onTrackChange',
        'onTransitionEnd',
        'onTransitionRun',
        'onTransitionStart',
        'onToggle',
        'onTouchCancel',
        'onTouchEnd',
        'onTouchLeave',
        'onTouchMove',
        'onTouchStart',
        'onTransitionCancel',
        'onTransitionEnd',
        'onUnload',
        'onUnhandledRejection',
        'onURLFlip',
        'onUserProximity',
        'onVolumeChange',
        'onWaiting',
        'onWebKitAnimationEnd',
        'onWebKitAnimationIteration',
        'onWebKitAnimationStart',
        'onWebKitFullScreenChange',
        'onWebKitFullScreenError',
        'onWebKitTransitionEnd',
        'onWheel',
    ];

    /**
     * https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#Event_Handlers
     *
     * @var string[]
     */
    private $_evil_attributes_regex = [
        'style',
        'xmlns:xdp',
        'formaction',
        'form',
        'xlink:href',
        'seekSegmentTime',
        'FSCommand',
    ];

    /**
     * @var string[]
     */
    private $_evil_html_tags = [
        'applet',
        'audio',
        'basefont',
        'base',
        'behavior',
        'bgsound',
        'blink',
        'body',
        'embed',
        'eval',
        'expression',
        'form',
        'frameset',
        'frame',
        'head',
        'html',
        'ilayer',
        'iframe',
        'input',
        'button',
        'select',
        'isindex',
        'layer',
        'link',
        'meta',
        'keygen',
        'object',
        'plaintext',
        'style',
        'script',
        'textarea',
        'title',
        'math',
        'noscript',
        'event-source',
        'vmlframe',
        'video',
        'source',
        'svg',
        'xml',
    ];

    /**
     * @var string
     */
    private $_spacing_regex = '(?:\s|"|\'|\+|&#x0[9A-F];|%0[9a-f])*?';

    /**
     * List of never allowed strings.
     *
     * @var string[]
     */
    private $_never_allowed_str = [
        'document.cookie'   => '',
        '(document).cookie' => '',
        'document.write'    => '',
        '(document).write'  => '',
        '.parentNode'       => '',
        '.innerHTML'        => '',
        '.appendChild'      => '',
        '-moz-binding'      => '',
        '<?'                => '&lt;?',
        '?>'                => '?&gt;',
        '<![CDATA['         => '&lt;![CDATA[',
        '<!ENTITY'          => '&lt;!ENTITY',
        '<!DOCTYPE'         => '&lt;!DOCTYPE',
        '<!ATTLIST'         => '&lt;!ATTLIST',
    ];

    /**
     * @var bool|null
     */
    private $_xss_found;

    /**
     * @var string
     */
    private $_cache_evil_attributes_regex_string = '';

    /**
     * @var string
     */
    private $_cache_never_allowed_regex_string = '';

    /**
     * @var string
     */
    private $_cache__evil_html_tags_str = '';

    /**
     * Compact any exploded words.
     *
     * <p>
     * <br />
     * INFO: This corrects words like:  j a v a s c r i p t
     * <br />
     * These words are compacted back to their correct state.
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _compact_exploded_javascript($str)
    {
        static $WORDS_CACHE;
        $WORDS_CACHE['chunk'] = [];
        $WORDS_CACHE['split'] = [];

        $words = [
            'javascript',
            '<script',
            '</script>',
            'base64',
            'document',
            'eval',
        ];

        // check if we need to perform the regex-stuff
        if (\strlen($str) <= 30) {
            $useStrPos = true;
        } else {
            $useStrPos = false;
        }

        foreach ($words as $word) {
            if (!isset($WORDS_CACHE['chunk'][$word])) {
                $WORDS_CACHE['chunk'][$word] = \substr(
                    \chunk_split($word, 1, $this->_spacing_regex),
                    0,
                    -\strlen($this->_spacing_regex)
                );

                $WORDS_CACHE['split'][$word] = \str_split($word);
            }

            if ($useStrPos) {
                foreach ($WORDS_CACHE['split'][$word] as $charTmp) {
                    if (\stripos($str, $charTmp) === false) {
                        continue 2;
                    }
                }
            }

            // We only want to do this when it is followed by a non-word character.
            // And if there are no char at the start of the string.
            //
            // That way valid stuff like "dealer to!" does not become "dealerto".

            $str = (string) \preg_replace_callback(
                '#(?<before>[^\p{L}]|^)(?<word>' . \str_replace(
                    ['#', '.'],
                    ['\#', '\.'],
                    $WORDS_CACHE['chunk'][$word]
                ) . ')(?<after>[^\p{L}@.!?\' ]|$)#ius',
                function ($matches) {
                    return $this->_compact_exploded_words_callback($matches);
                },
                $str
            );
        }

        return $str;
    }

    /**
     * Compact exploded words.
     *
     * <p>
     * <br />
     * INFO: Callback method for xss_clean() to remove whitespace from things like 'j a v a s c r i p t'.
     * </p>
     *
     * @param string[] $matches
     *
     * @return  string
     */
    private function _compact_exploded_words_callback($matches)
    {
        return $matches['before'] . \preg_replace(
            '/' . $this->_spacing_regex . '/ius',
            '',
            $matches['word']
        ) . $matches['after'];
    }

    /**
     * HTML-Entity decode callback.
     *
     * @param string[] $match
     *
     * @return string
     */
    private function _decode_entity($match)
    {
        // init
        $str = $match[0];

        // protect GET variables without XSS in URLs
        $needProtection = true;
        if (\strpos($str, '=') !== false) {
            $strCopy = $str;
            $matchesTmp = [];
            while (\preg_match("/[?|&]?[\p{L}\d_\-\[\]]+\s*=\s*([\"'])(?<attr>[^\1]*?)\\1/u", $strCopy, $matches)) {
                $matchesTmp[] = $matches;
                $strCopy = \str_replace($matches[0], '', $strCopy);

                if (\substr_count($strCopy, '"') <= 1 && \substr_count($strCopy, '\'') <= 1) {
                    break;
                }
            }

            if ($strCopy !== $str) {
                $needProtection = false;
                foreach ($matchesTmp as $matches) {
                    if (isset($matches['attr'])) {
                        $tmpAntiXss = clone $this;

                        $urlPartClean = $tmpAntiXss->xss_clean((string) $matches['attr']);

                        if ($tmpAntiXss->isXssFound() === true) {
                            $this->_xss_found = true;

                            $urlPartClean = \str_replace(['&lt;', '&gt;'], [self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], $urlPartClean);
                            $urlPartClean = rawurldecode($urlPartClean);
                            $urlPartClean = \str_replace([self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], ['&lt;', '&gt;'], $urlPartClean);

                            $str = \str_ireplace($matches['attr'], $urlPartClean, $str);
                        }
                    }
                }
            }
        }

        if ($needProtection) {
            $str = \str_replace(['&lt;', '&gt;'], [self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], $str);
            $str = $this->_entity_decode(rawurldecode($str));
            $str = \str_replace([self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], ['&lt;', '&gt;'], $str);
        }

        return $str;
    }

    /**
     * Decode the html-tags but keep links without XSS.
     *
     * @param string $str
     *
     * @return string
     */
    private function _decode_string($str)
    {
        // init
        $regExForHtmlTags = '/<\p{L}+(?:[^>"\']|(["\']).*\1)*>/usU';

        if (
            \strpos($str, '<') !== false
            &&
            \preg_match($regExForHtmlTags, $str)
        ) {
            $str = (string) \preg_replace_callback(
                $regExForHtmlTags,
                function ($matches) {
                    return $this->_decode_entity($matches);
                },
                $str
            );
        } else {
            $str = rawurldecode($str);
        }

        return $str;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private function _do($str)
    {
        $str = (string) $str;
        $strInt = (int) $str;
        $strFloat = (float) $str;
        if (
            !$str
            ||
            (string) $strInt === $str
            ||
            (string) $strFloat === $str
        ) {
            // no xss found
            if ($this->_xss_found !== true) {
                $this->_xss_found = false;
            }

            return $str;
        }

        // decode UTF-7 characters
        $str = $this->_repack_utf7($str);

        // decode the string
        $str = $this->_decode_string($str);

        // backup the string (for later comparison)
        $str_backup = $str;

        // process
        do {
            // backup the string (for the loop)
            $str_backup_loop = $str;

            // correct words before the browser will do it
            $str = $this->_compact_exploded_javascript($str);

            // remove disallowed javascript calls in links, images etc.
            $str = $this->_remove_disallowed_javascript($str);

            // remove strings that are never allowed
            $str = $this->_do_never_allowed($str);

            // remove evil attributes such as style, onclick and xmlns
            $str = $this->_remove_evil_attributes($str);

            // sanitize naughty JavaScript elements
            $str = $this->_sanitize_naughty_javascript($str);

            // sanitize naughty HTML elements
            $str = $this->_sanitize_naughty_html($str);

            // final clean up
            //
            // -> This adds a bit of extra precaution in case something got through the above filters.
            $str = $this->_do_never_allowed_afterwards($str);
        } while ($str_backup_loop !== $str);

        // check for xss
        if ($this->_xss_found !== true) {
            $this->_xss_found = !($str_backup === $str);
        }

        return $str;
    }

    /**
     * Remove never allowed strings.
     *
     * @param string $str
     *
     * @return string
     */
    private function _do_never_allowed($str)
    {
        static $NEVER_ALLOWED_CACHE = [];

        $NEVER_ALLOWED_CACHE['keys'] = null;

        if ($NEVER_ALLOWED_CACHE['keys'] === null) {
            $NEVER_ALLOWED_CACHE['keys'] = \array_keys($this->_never_allowed_str);
        }

        $str = \str_ireplace(
            $NEVER_ALLOWED_CACHE['keys'],
            $this->_never_allowed_str,
            $str
        );

        // ---

        $replaceNeverAllowedCall = [];
        foreach ($this->_never_allowed_call_strings as $call) {
            if (\stripos($str, $call) !== false) {
                $replaceNeverAllowedCall[] = $call;
            }
        }
        if (\count($replaceNeverAllowedCall) > 0) {
            $str = (string) \preg_replace(
                '#([^\p{L}]|^)(?:' . \implode('|', $replaceNeverAllowedCall) . ')\s*:(?:.*?([/\\\;()\'">]|$))#ius',
                '$1' . '' . '$2',
                $str
            );
        }

        // ---

        $regex_combined = [];
        foreach ($this->_never_allowed_regex as $regex => $replacement) {
            if ($replacement === '') {
                $regex_combined[] = $regex;

                continue;
            }

            $str = (string) \preg_replace(
                '#' . $regex . '#iUus',
                $replacement,
                $str
            );
        }

        if (!$this->_cache_never_allowed_regex_string || $regex_combined !== []) {
            $this->_cache_never_allowed_regex_string = \implode('|', $regex_combined);
        }

        if ($this->_cache_never_allowed_regex_string) {
            $str = (string) \preg_replace(
                '#' . $this->_cache_never_allowed_regex_string . '#ius',
                '',
                $str
            );
        }

        return $str;
    }

    /**
     * @return array
     *
     * @phpstan-return array<string, list<string>>
     */
    private function _get_never_allowed_on_events_afterwards_chunks()
    {
        // init
        $array = [];

        foreach ($this->_never_allowed_on_events_afterwards as $event) {
            $array[$event[0] . $event[1] . $event[2]][] = $event;
        }

        return $array;
    }

    /**
     * Remove never allowed string, afterwards.
     *
     * <p>
     * <br />
     * INFO: clean-up also some string, if there is no html-tag
     * </p>
     *
     * @param string $str
     *
     * @return  string
     */
    private function _do_never_allowed_afterwards($str)
    {
        if (\stripos($str, 'on') !== false) {
            foreach ($this->_get_never_allowed_on_events_afterwards_chunks() as $eventNameBeginning => $events) {
                if (\stripos($str, $eventNameBeginning) === false) {
                    continue;
                }

                foreach ($events as $event) {
                    if (\stripos($str, $event) === false) {
                        continue;
                    }

                    $regex = '(?<before>[^\p{L}@.!?>]|^)(?:' . \implode('|', $events) . ')(?<after>\(.*?\)|.*?>|(?:\s|\[.*?\])*?=(?:\s|\[.*?\])*?|(?:\s|\[.*?\])*?&equals;(?:\s|\[.*?\])*?|[^\p{L}]*?=[^\p{L}]*?|[^\p{L}]*?&equals;[^\p{L}]*?|$|\s*?>*?$)';

                    do {
                        $count = $temp_count = 0;

                        $str = (string) \preg_replace(
                            '#' . $regex . '#ius',
                            '$1' . '' . '$2',
                            $str,
                            -1,
                            $temp_count
                        );
                        $count += $temp_count;
                    } while ($count);

                    break;
                }
            }
        }

        return (string) \str_ireplace(
            $this->_never_allowed_str_afterwards,
            '',
            $str
        );
    }

    /**
     * Entity-decoding.
     *
     * @param string $str
     *
     * @return string
     */
    private function _entity_decode($str)
    {
        static $HTML_ENTITIES_CACHE;

        $flags = ENT_QUOTES | ENT_HTML5 | ENT_DISALLOWED | ENT_SUBSTITUTE;

        // decode-again, for e.g. HHVM or miss configured applications ...
        if (
            \strpos($str, '&') !== false
            &&
            \preg_match_all('/(?<html_entity>&[A-Za-z]{2,};{0})/', $str, $matches)
        ) {
            if ($HTML_ENTITIES_CACHE === null) {
                // links:
                // - http://dev.w3.org/html5/html-author/charref
                // - http://www.w3schools.com/charsets/ref_html_entities_n.asp
                $entitiesSecurity = [
                    '&#x00000;'          => '',
                    '&#0;'               => '',
                    '&#x00001;'          => '',
                    '&#1;'               => '',
                    '&nvgt;'             => '',
                    '&#61253;'           => '',
                    '&#x0EF45;'          => '',
                    '&shy;'              => '',
                    '&#x000AD;'          => '',
                    '&#173;'             => '',
                    '&colon;'            => ':',
                    '&#x0003A;'          => ':',
                    '&#58;'              => ':',
                    '&lpar;'             => '(',
                    '&#x00028;'          => '(',
                    '&#40;'              => '(',
                    '&rpar;'             => ')',
                    '&#x00029;'          => ')',
                    '&#41;'              => ')',
                    '&quest;'            => '?',
                    '&#x0003F;'          => '?',
                    '&#63;'              => '?',
                    '&sol;'              => '/',
                    '&#x0002F;'          => '/',
                    '&#47;'              => '/',
                    '&apos;'             => '\'',
                    '&#x00027;'          => '\'',
                    '&#039;'             => '\'',
                    '&#39;'              => '\'',
                    '&#x27;'             => '\'',
                    '&bsol;'             => '\'',
                    '&#x0005C;'          => '\\',
                    '&#92;'              => '\\',
                    '&comma;'            => ',',
                    '&#x0002C;'          => ',',
                    '&#44;'              => ',',
                    '&period;'           => '.',
                    '&#x0002E;'          => '.',
                    '&quot;'             => '"',
                    '&QUOT;'             => '"',
                    '&#x00022;'          => '"',
                    '&#34;'              => '"',
                    '&grave;'            => '`',
                    '&DiacriticalGrave;' => '`',
                    '&#x00060;'          => '`',
                    '&#96;'              => '`',
                    '&#46;'              => '.',
                    '&equals;'           => '=',
                    '&#x0003D;'          => '=',
                    '&#61;'              => '=',
                    '&newline;'          => "\n",
                    '&#x0000A;'          => "\n",
                    '&#10;'              => "\n",
                    '&tab;'              => "\t",
                    '&#x00009;'          => "\t",
                    '&#9;'               => "\t",
                ];

                $HTML_ENTITIES_CACHE = \array_merge(
                    $entitiesSecurity,
                    \array_flip(\get_html_translation_table(HTML_ENTITIES, $flags))
                );
            }

            $search = [];
            $replace = [];
            foreach ($matches['html_entity'] as $match) {
                $match .= ';';
                if (isset($HTML_ENTITIES_CACHE[$match])) {
                    $search[$match] = $match;
                    $replace[$match] = $HTML_ENTITIES_CACHE[$match];
                }
            }

            if (\count($replace) > 0) {
                $str = \str_ireplace($search, $replace, $str);
            }
        }

        return $str;
    }

    /**
     * Filters tag attributes for consistency and safety.
     *
     * @param string $str
     *
     * @return string
     */
    private function _filter_attributes($str)
    {
        if ($str === '') {
            return '';
        }

        if (\strpos($str, '=') !== false) {
            $matchesTmp = [];
            while (\preg_match('#\s*[\p{L}\d_\-\[\]]+\s*=\s*(["\'])(?:[^\1]*?)\\1#u', $str, $matches)) {
                $matchesTmp[] = $matches[0];
                $str = \str_replace($matches[0], '', $str);

                if (\substr_count($str, '"') <= 1 && \substr_count($str, '\'') <= 1) {
                    break;
                }
            }
            $out = \implode('', $matchesTmp);
        } else {
            $out = $str;
        }

        return $out;
    }

    /**
     * Callback method for xss_clean() to sanitize links.
     *
     * <p>
     * <br />
     * INFO: This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on link-heavy strings.
     * </p>
     *
     * @param string[] $match
     *
     * @return string
     */
    private function _js_link_removal_callback($match)
    {
        return $this->_js_removal_callback($match, 'href');
    }

    /**
     * Callback method for xss_clean() to sanitize tags.
     *
     * <p>
     * <br />
     * INFO: This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings.
     * </p>
     *
     * @param string[]  $match
     * @param string $search
     *
     * @return string
     */
    private function _js_removal_callback($match, $search)
    {
        if (!$match[0]) {
            return '';
        }

        $replacer = $this->_filter_attributes($match[1]);

        // filter for "$search"-attributes
        if (\stripos($match[1], $search . '=') !== false) {
            $pattern = '#' . $search . '=(?<wrapper>[\'|"])(?<link>.*)(?:\g{wrapper})#isU';
            $matchInner = [];
            $foundSomethingBad = false;
            if (\preg_match($pattern, $match[1], $matchInner)) {
                $needProtection = true;
                $matchInner['link'] = \str_replace(' ', '%20', $matchInner['link']);

                if (
                    \strpos($matchInner[0], 'script') === false
                    &&
                    \strpos(\str_replace(['http://', 'https://'], '', $matchInner[0]), ':') === false
                    &&
                    (
                        \filter_var($matchInner['link'], \FILTER_VALIDATE_URL) !== false
                        ||
                        \filter_var('https://localhost.localdomain/' . $matchInner['link'], \FILTER_VALIDATE_URL) !== false
                    )
                ) {
                    $needProtection = false;
                }

                if ($needProtection) {
                    $tmpAntiXss = clone $this;

                    $tmpAntiXss->xss_clean((string) $matchInner[0]);

                    if ($tmpAntiXss->isXssFound() === true) {
                        $foundSomethingBad = true;
                        $this->_xss_found = true;

                        $replacer = (string) \preg_replace(
                            $pattern,
                            $search . '="' . '' . '"',
                            $replacer
                        );
                    }
                }
            }

            if (!$foundSomethingBad) {
                // filter for javascript
                $patternTmp = '';
                foreach ($this->_never_allowed_call_strings as $callTmp) {
                    if (\stripos($match[0], $callTmp) !== false) {
                        $patternTmp .= $callTmp . ':|';
                    }
                }
                $pattern = '#' . $search . '=.*(?:' . $patternTmp . \implode('|', $this->_never_allowed_js_callback_regex) . ')#ius';
                $matchInner = [];
                if (\preg_match($pattern, $match[1], $matchInner)) {
                    $replacer = (string) \preg_replace(
                        $pattern,
                        $search . '="' . '' . '"',
                        $replacer
                    );
                }
            }
        }

        if (
            \substr($match[0], -3) === ' />'
            &&
            \substr($match[1], -2) === ' /'
            &&
            \substr($replacer, -2) !== ' /'
        ) {
            $replacer .= ' /';
        } elseif (
            \substr($match[0], -2) === '/>'
            &&
            \substr($match[1], -1) === '/'
            &&
            \substr($replacer, -1) !== '/'
        ) {
            $replacer .= '/';
        }


        return \str_ireplace($match[1], $replacer, (string) $match[0]);
    }

    /**
     * Callback method for xss_clean() to sanitize image tags.
     *
     * <p>
     * <br />
     * INFO: This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings.
     * </p>
     *
     * @param string[] $match
     *
     * @return string
     */
    private function _js_src_removal_callback(array $match)
    {
        return $this->_js_removal_callback($match, 'src');
    }

    /**
     * Remove disallowed Javascript in links or img tags
     *
     * <p>
     * <br />
     * We used to do some version comparisons and use of stripos(),
     * but it is dog slow compared to these simplified non-capturing
     * preg_match(), especially if the pattern exists in the string
     * </p>
     *
     * <p>
     * <br />
     * Note: It was reported that not only space characters, but all in
     * the following pattern can be parsed as separators between a tag name
     * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
     * ... however, UTF8::clean() above already strips the
     * hex-encoded ones, so we'll skip them below.
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _remove_disallowed_javascript($str)
    {
        do {
            $original = $str;

            if (\stripos($str, '<a') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<a[^\p{L}@>]+([^>]*?)(?:>|$)#iu',
                    function ($matches) {
                        return $this->_js_link_removal_callback($matches);
                    },
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = \preg_replace_callback(
                        '#<a[^\p{L}@>]+([^>]*)(?:>|$)#iu',
                        function ($matches) {
                            return $this->_js_link_removal_callback($matches);
                        },
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<img') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<img[^\p{L}@]+([^>]*?)(?:\s?/?>|$)#iu',
                    function ($matches) {
                        if (
                            \strpos($matches[1], 'base64') !== false
                            &&
                            \preg_match("/([\"'])?data\s*:\s*(?:image\s*\/.*)[^\1]*base64[^\1]*,[^\1]*\1?/iUus", $matches[1])
                        ) {
                            return $matches[0];
                        }

                        return $this->_js_src_removal_callback($matches);
                    },
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = (string) \preg_replace_callback(
                        '#<img[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                        function ($matches) {
                            if (
                                \strpos($matches[1], 'base64') !== false
                                &&
                                \preg_match("/([\"'])?data\s*:\s*(?:image\s*\/.*)[^\1]*base64[^\1]*,[^\1]*\1?/iUus", $matches[1])
                            ) {
                                return $matches[0];
                            }

                            return $this->_js_src_removal_callback($matches);
                        },
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<audio') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<audio[^\p{L}@]+([^>]*?)(?:\s?/?>|$)#iu',
                    function ($matches) {
                        return $this->_js_src_removal_callback($matches);
                    },
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = (string) \preg_replace_callback(
                        '#<audio[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                        function ($matches) {
                            return $this->_js_src_removal_callback($matches);
                        },
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<video') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<video[^\p{L}@]+([^>]*?)(?:\s?/?>|$)#iu',
                    function ($matches) {
                        return $this->_js_src_removal_callback($matches);
                    },
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = \preg_replace_callback(
                        '#<video[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                        function ($matches) {
                            return $this->_js_src_removal_callback($matches);
                        },
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<source') !== false) {
                $str = (string) \preg_replace_callback(
                    '#<source[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                    function ($matches) {
                        return $this->_js_src_removal_callback($matches);
                    },
                    $str
                );
            }

            if (\stripos($str, 'script') !== false) {
                // INFO: US-ASCII: ¼ === <
                $str = (string) \preg_replace(
                    '#(?:%3C|¼|<)\s*script[^\p{L}@]+(?:[^>]*)(?:\s?/?(?:%3E|¾|>)|$)#iu',
                    '',
                    $str
                );
            }

            if (\stripos($str, 'script') !== false) {
                // INFO: US-ASCII: ¼ === <
                $str = (string) \preg_replace(
                    '#(?:%3C|¼|<)[^\p{L}@]*/*[^\p{L}@]*(?:script[^\p{L}@]+).*(?:%3E|¾|>)?#iUus',
                    '',
                    $str
                );
            }
        } while ($original !== $str);

        return (string) $str;
    }

    /**
     * Remove Evil HTML Attributes (like event handlers and style).
     *
     * It removes the evil attribute and either:
     *
     *  - Everything up until a space. For example, everything between the pipes:
     *
     * <code>
     *   <a |style=document.write('hello');alert('world');| class=link>
     * </code>
     *
     *  - Everything inside the quotes. For example, everything between the pipes:
     *
     * <code>
     *   <a |style="document.write('hello'); alert('world');"| class="link">
     * </code>
     *
     * @param string $str <p>The string to check.</p>
     *
     * @return string
     *                <p>The string with the evil attributes removed.</p>
     */
    private function _remove_evil_attributes($str)
    {
        // replace style-attribute, first (if needed)
        if (
            \stripos($str, 'style') !== false
            &&
            \in_array('style', $this->_evil_attributes_regex, true)
        ) {
            do {
                $count = $temp_count = 0;

                $str = (string) \preg_replace(
                    '/(<[^>]+)(?<!\p{L})(style\s*=\s*"(?:[^"]*?)"|style\s*=\s*\'(?:[^\']*?)\')/iu',
                    '$1' . '',
                    $str,
                    -1,
                    $temp_count
                );
                $count += $temp_count;
            } while ($count);
        }

        if (!$this->_cache_evil_attributes_regex_string) {
            $this->_cache_evil_attributes_regex_string = \implode('|', $this->_evil_attributes_regex);
            $this->_cache_evil_attributes_regex_string .= '|' . \implode('\w*|', $this->_never_allowed_on_events_afterwards);
        }

        do {
            $count = $temp_count = 0;

            // find occurrences of illegal attribute strings with and without quotes (" and ' are octal quotes)
            $regex = '/(.*)((?:<[^>]+)(?<!\p{L}))(?:' . $this->_cache_evil_attributes_regex_string . ')(?:\s*=\s*)(?:\'(?:.*?)\'|"(?:.*?)")(.*)/ius';
            $strTmp = \preg_replace(
                $regex,
                '$1$2' . '' . '$3$4',
                $str,
                -1,
                $temp_count
            );
            if ($strTmp === null) {
                $regex = '/(?:' . $this->_cache_evil_attributes_regex_string . ')(?:\s*=\s*)(?:\'(?:.*?)\'|"(?:.*?)")/ius';
                $strTmp = \preg_replace(
                    $regex,
                    '',
                    $str,
                    -1,
                    $temp_count
                );
            }
            $str = (string)$strTmp;
            $count += $temp_count;

            $regex =  '/(.*?)(<[^>]+)(?<!\p{L})(?:' . $this->_cache_evil_attributes_regex_string . ')\s*=\s*(?:[^\s>]*)/ius';
            $strTmp = \preg_replace(
                $regex,
                '$1$2' . '' . '$3',
                $str,
                -1,
                $temp_count
            );
            if ($strTmp === null) {
                $regex =  '/(?<!\p{L})(?:' . $this->_cache_evil_attributes_regex_string . ')\s*=\s*(?:[^\s>]*)(.*?)/ius';
                $strTmp = \preg_replace(
                    $regex,
                    '$1$2' . '' . '$3',
                    $str,
                    -1,
                    $temp_count
                );
            }
            $str = (string)$strTmp;
            $count += $temp_count;
        } while ($count);

        return (string) $str;
    }

    /**
     * UTF-7 decoding function.
     *
     * @param string $str <p>HTML document for recode ASCII part of UTF-7 back to ASCII.</p>
     *
     * @return string
     */
    private function _repack_utf7($str)
    {
        if (\strpos($str, '-') === false) {
            return $str;
        }

        return (string) \preg_replace_callback(
            '#\+([\p{L}\d]+)-#iu',
            function ($matches) {
                return $this->_repack_utf7_callback($matches);
            },
            $str
        );
    }

    /**
     * Additional UTF-7 decoding function.
     *
     * @param string[] $strings <p>Array of strings for recode ASCII part of UTF-7 back to ASCII.</p>
     *
     * @return string
     */
    private function _repack_utf7_callback($strings)
    {
        $strTmp = \base64_decode($strings[1], true);

        if ($strTmp === false) {
            return $strings[0];
        }

        if (\rtrim(\base64_encode($strTmp), '=') !== \rtrim($strings[1], '=')) {
            return $strings[0];
        }

        $string = (string) \preg_replace_callback(
            '/^((?:\x00.)*?)((?:[^\x00].)+)/us',
            function ($matches) {
                return $this->_repack_utf7_callback_back($matches);
            },
            $strTmp
        );

        return (string) \preg_replace(
            '/\x00(.)/us',
            '$1',
            $string
        );
    }

    /**
     * Additional UTF-7 encoding function.
     *
     * @param string $str <p>String for recode ASCII part of UTF-7 back to ASCII.</p>
     *
     * @return string
     */
    private function _repack_utf7_callback_back($str)
    {
        return $str[1] . '+' . \rtrim(\base64_encode($str[2]), '=') . '-';
    }

    /**
     * Sanitize naughty HTML elements.
     *
     * <p>
     * <br />
     *
     * If a tag containing any of the words in the list
     * below is found, the tag gets converted to entities.
     *
     * <br /><br />
     *
     * So this: <blink>
     * <br />
     * Becomes: &lt;blink&gt;
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _sanitize_naughty_html($str)
    {
        // init
        $strEnd = '';

        do {
            $original = $str;

            if (
                \strpos($str, '<') === false
                &&
                \strpos($str, '>') === false
            ) {
                return $str;
            }

            if (!$this->_cache__evil_html_tags_str) {
                $this->_cache__evil_html_tags_str = \implode('|', $this->_evil_html_tags);
            }

            $str = (string) \preg_replace_callback(
                '#<(?<start>/*\s*)(?<tagName>' . $this->_cache__evil_html_tags_str . ')(?<end>[^><]*)(?<rest>[><]*)#ius',
                function ($matches) {
                    return $this->_sanitize_naughty_html_callback($matches);
                },
                $str
            );

            if (\strpos($str, '<') === false) {
                return $str;
            }

            if (
                $this->_xss_found
                &&
                \trim($str) === '<'
            ) {
                return '';
            }

            $str = (string) \preg_replace_callback(
                '#<(?!!--|!\[)((?<start>/*\s*)((?<tagName>[\p{L}:]+)(?=[^\p{L}]|$|)|.+)[^\s"\'\p{L}>/=]*[^>]*)(?<closeTag>>)?#iusS', // tags without comments
                function ($matches) {
                    if (
                        $this->_do_not_close_html_tags !== []
                        &&
                        isset($matches['tagName'])
                        &&
                        \in_array($matches['tagName'], $this->_do_not_close_html_tags, true)
                    ) {
                        return $matches[0];
                    }

                    return $this->_close_html_callback($matches);
                },
                $str
            );

            if ($str === $strEnd) {
                return (string) $str;
            }

            $strEnd = $str;
        } while ($original !== $str);

        return (string) $str;
    }

    /**
     * @param string[] $matches
     *
     * @return string
     */
    private function _close_html_callback($matches)
    {
        if (empty($matches['closeTag'])) {
            // allow e.g. "< $2.20" and e.g. "< 1 year"
            if (\preg_match('/^[ .,\d=%€$₢₣£₤₶ℳ₥₦₧₨රුரூ௹रू₹૱₩₪₸₫֏₭₺₼₮₯₰₷₱﷼₲₾₳₴₽₵₡¢¥円৳元៛₠¤฿؋]*$|^[ .,\d=%€$₢₣£₤₶ℳ₥₦₧₨රුரூ௹रू₹૱₩₪₸₫֏₭₺₼₮₯₰₷₱﷼₲₾₳₴₽₵₡¢¥円৳元៛₠¤฿؋]+\p{L}*\s*$/u', $matches[1])) {
                return '<' . \str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[1]);
            }

            return '&lt;' . \str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[1]);
        }

        return '<' . \str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[1]) . '>';
    }

    /**
     * Sanitize naughty HTML.
     *
     * <p>
     * <br />
     * Callback method for AntiXSS->sanitize_naughty_html() to remove naughty HTML elements.
     * </p>
     *
     * @param string[] $matches
     *
     * @return string
     */
    private function _sanitize_naughty_html_callback($matches)
    {
        $fullMatch = $matches[0];

        // skip some edge-cases
        /** @noinspection NotOptimalIfConditionsInspection */
        if (
            (
                \strpos($fullMatch, '=') === false
                &&
                \strpos($fullMatch, ' ') === false
                &&
                \strpos($fullMatch, ':') === false
                &&
                \strpos($fullMatch, '/') === false
                &&
                \strpos($fullMatch, '\\') === false
                &&
                \stripos($fullMatch, '<' . $matches['tagName'] . '>') !== 0
                &&
                \stripos($fullMatch, '</' . $matches['tagName'] . '>') !== 0
                &&
                \stripos($fullMatch, '<' . $matches['tagName'] . '<') !== 0
            )
            ||
            \preg_match('/<\/?' . $matches['tagName'] . '\p{L}+>/ius', $fullMatch) === 1
        ) {
            return $fullMatch;
        }

        return '&lt;' . $matches['start'] . $matches['tagName'] . $matches['end'] // encode opening brace
               // encode captured opening or closing brace to prevent recursive vectors
               . \str_replace(
                   [
                       '>',
                   ],
                   [
                       '&gt;',
                   ],
                   $matches['rest']
               );
    }

    /**
     * Sanitize naughty scripting elements
     *
     * <p>
     * <br />
     *
     * Similar to above, only instead of looking for
     * tags it looks for PHP and JavaScript commands
     * that are disallowed. Rather than removing the
     * code, it simply converts the parenthesis to entities
     * rendering the code un-executable.
     *
     * <br /><br />
     *
     * For example:  <pre>eval('some code')</pre>
     * <br />
     * Becomes:      <pre>eval&#40;'some code'&#41;</pre>
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _sanitize_naughty_javascript($str)
    {
        if (\strpos($str, '(') !== false) {
            $patterns = [
                'alert',
                'prompt',
                'confirm',
                'cmd',
                'passthru',
                'eval',
                'exec',
                'execScript',
                'setTimeout',
                'setInterval',
                'setImmediate',
                'expression',
                'system',
                'fopen',
                'fsockopen',
                'file',
                'file_get_contents',
                'readfile',
                'unlink',
            ];

            $found = false;
            foreach ($patterns as $pattern) {
                if (\strpos($str, $pattern) !== false) {
                    $found = true;

                    break;
                }
            }

            if ($found === true) {
                $str = (string) \preg_replace(
                    '#(?<!\p{L})(' . \implode('|', $patterns) . ')(\s*)\((.*)\)#uisU',
                    '\\1\\2&#40;\\3&#41;',
                    $str
                );
            }
        }

        return (string) $str;
    }

    /**
     * Check if the "AntiXSS->xss_clean()"-method found an XSS attack in the last run.
     *
     * @return bool|null
     *                   <p>Will return null if the "xss_clean()" wasn't running at all.</p>
     */
    public function isXssFound()
    {
        return $this->_xss_found;
    }

    /**
     * XSS Clean
     */
    public function xss_clean(string $str): string
    {
        // reset
        $this->_xss_found = null;

        $old_str_backup = $str;

        // process
        do {
            $old_str = $str;
            $str = $this->_do($str);
        } while ($old_str !== $str);

        // keep the old value, if there wasn't any XSS attack
        if ($this->_xss_found !== true) {
            $str = $old_str_backup;
        }

        return $str;
    }
}
