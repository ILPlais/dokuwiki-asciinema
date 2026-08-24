<?php

/**
 * Configuration metadata for the asciinema plugin
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Lionel PLAIS <lionel.plais@ilp-web.net>
 */

$meta['register_mime'] = ['onoff'];
$meta['load_fonts'] = ['onoff'];
$meta['default_size'] = ['string'];
$meta['theme'] = ['multichoice', '_choices' => [
	'auto/asciinema',
	'asciinema',
	'dracula',
	'gruvbox-dark',
	'monokai',
	'nord',
	'seti',
	'solarized-dark',
	'solarized-light',
	'tango',
]];
$meta['fit'] = ['multichoice', '_choices' => ['width', 'height', 'both', 'none']];
$meta['controls'] = ['multichoice', '_choices' => ['auto', 'always', 'never']];
$meta['autoplay'] = ['onoff'];
$meta['loop'] = ['onoff'];
$meta['preload'] = ['onoff'];
$meta['speed'] = ['string'];
$meta['idle_time_limit'] = ['string'];
$meta['font_family'] = ['string'];
$meta['font_size'] = ['string'];
