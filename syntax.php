<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\File\MediaResolver;

/**
 * DokuWiki Plugin asciinema (Syntax Component)
 *
 * Renders asciinema recordings, either from a local .cast file played by the
 * bundled JavaScript player, or embedded from an asciinema server such as
 * asciinema.org.
 *
 *   {{asciinema>linux.cast}}
 *   {{asciinema>https://asciinema.org/a/569727}}
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Lionel PLAIS <lionel.plais@ilp-web.net>
 */
class syntax_plugin_asciinema extends SyntaxPlugin
{
	/** Named sizes, as [width, height] */
	protected $sizes = [
		'small' => ['255px', '143px'],
		'medium' => ['425px', '239px'],
		'large' => ['520px', '293px'],
		'full' => ['100%', ''],
		'half' => ['50%', ''],
	];

	/** Alignment indexed by (leading space) + 2 * (trailing space) */
	protected $alignments = [
		0 => 'none',
		1 => 'right',
		2 => 'left',
		3 => 'center',
	];

	/** Terminal themes shipped with the bundled player stylesheet */
	protected $themes = [
		'asciinema',
		'dracula',
		'gruvbox-dark',
		'monokai',
		'nord',
		'seti',
		'solarized-dark',
		'solarized-light',
		'tango',
	];

	/** @inheritDoc */
	public function getType()
	{
		return 'substition';
	}

	/** @inheritDoc */
	public function getPType()
	{
		return 'block';
	}

	/**
	 * @inheritDoc
	 * Must be lower than 320, the sort of DokuWiki's own {{...}} media mode.
	 */
	public function getSort()
	{
		return 319;
	}

	/** @inheritDoc */
	public function connectTo($mode)
	{
		$this->Lexer->addSpecialPattern('\{\{ ?asciinema>[^}]*\}\}', $mode, 'plugin_asciinema');
	}

	/** @inheritDoc */
	public function handle($match, $state, $pos, Doku_Handler $handler)
	{
		$inner = substr($match, 2, -2);

		$leftSpace = (substr($inner, 0, 1) === ' ');
		$rightSpace = (substr($inner, -1) === ' ');

		// drop the "asciinema>" marker
		$inner = substr(trim($inner), strlen('asciinema>'));

		$data = [
			'type' => 'none',
			'src' => '',
			'server' => '',
			'cast' => '',
			'exists' => false,
			'align' => $this->alignments[($leftSpace ? 1 : 0) + ($rightSpace ? 2 : 0)],
			'width' => '',
			'height' => '',
			'title' => '',
			'opts' => [],
		];

		// an optional caption is separated by a pipe
		$pipe = strpos($inner, '|');
		if ($pipe !== false) {
			$data['title'] = trim(substr($inner, $pipe + 1));
			$inner = substr($inner, 0, $pipe);
		}
		$inner = trim($inner);

		// options follow the last question mark so that URLs stay intact
		$mark = strrpos($inner, '?');
		if ($mark !== false) {
			$this->parseParams(substr($inner, $mark + 1), $data);
			$inner = substr($inner, 0, $mark);
		}

		$src = trim($inner);
		if ($src === '') {
			return $data;
		}

		if (preg_match('#^https?://#i', $src)) {
			if (
				!preg_match('#\.(cast|json)$#i', $src) &&
				preg_match('#^(https?://[\w.-]+(?::\d+)?)/a/([\w-]+)(?:\.js)?/?$#i', $src, $parts)
			) {
				$data['type'] = 'embed';
				$data['server'] = $parts[1];
				$data['cast'] = $parts[2];
			} else {
				$data['type'] = 'url';
				$data['src'] = $src;
			}
			return $data;
		}

		$data['type'] = 'local';
		[$data['src'], $data['exists']] = $this->resolveMedia($src);

		return $data;
	}

	/** @inheritDoc */
	public function render($mode, Doku_Renderer $renderer, $data)
	{
		if ($mode === 'metadata') {
			if ($data['type'] === 'local' && isset($renderer->meta)) {
				$renderer->meta['relation']['media'][$data['src']] = $data['exists'];
			}
			return true;
		}

		if ($mode !== 'xhtml') {
			return false;
		}

		if ($data['type'] === 'none') {
			$renderer->doc .= '<div class="plugin_asciinema__error">' .
				hsc($this->getLang('nosource')) . '</div>';
			return true;
		}

		if ($data['type'] === 'local' && !$data['exists']) {
			$renderer->doc .= '<div class="plugin_asciinema__error">' .
				hsc(sprintf($this->getLang('notfound'), $data['src'])) . '</div>';
			return true;
		}

		$renderer->doc .= $this->openWrapper($data);
		$renderer->doc .= ($data['type'] === 'embed')
			? $this->embedScript($data)
			: $this->playerElement($data);
		if ($data['title'] !== '') {
			$renderer->doc .= '<div class="plugin_asciinema__caption">' . hsc($data['title']) . '</div>';
		}
		$renderer->doc .= '</div>';

		return true;
	}

	/**
	 * Resolve a wiki media id relative to the page currently being parsed
	 *
	 * @param string $src
	 * @return array [resolved id, exists]
	 */
	protected function resolveMedia($src)
	{
		global $ID;

		if (class_exists(MediaResolver::class)) {
			$id = (new MediaResolver($ID))->resolveId($src);
			return [$id, file_exists(mediaFN($id))];
		}

		$id = $src;
		$exists = false;
		resolve_mediaid(getNS($ID), $id, $exists);
		return [$id, $exists];
	}

	/**
	 * The container carrying alignment and size
	 *
	 * @param array $data
	 * @return string
	 */
	protected function openWrapper($data)
	{
		$classes = ['plugin_asciinema'];
		switch ($data['align']) {
			case 'left':
				$classes[] = 'medialeft';
				break;
			case 'right':
				$classes[] = 'mediaright';
				break;
			case 'center':
				$classes[] = 'mediacenter';
				break;
			default:
				$classes[] = 'media';
		}

		$width = $data['width'] !== '' ? $data['width'] : $this->defaultWidth();
		$style = '';
		if ($width !== '') {
			$style .= 'width:' . $width . ';';
		}
		if ($data['height'] !== '') {
			$style .= 'height:' . $data['height'] . ';';
		}

		return '<div class="' . implode(' ', $classes) . '"' .
			($style !== '' ? ' style="' . hsc($style) . '"' : '') . '>';
	}

	/**
	 * Placeholder picked up by script.js, which lazily loads the bundled player
	 *
	 * @param array $data
	 * @return string
	 */
	protected function playerElement($data)
	{
		$url = ($data['type'] === 'local')
			? ml($data['src'], '', true, '&')
			: $data['src'];

		$opts = array_merge($this->defaultOptions(), $data['opts']);

		$html = '<div class="plugin_asciinema__player"' .
			' data-asciinema-src="' . hsc($url) . '"' .
			' data-asciinema-opts="' . hsc(json_encode($opts)) . '"';

		if ($this->getConf('load_fonts')) {
			$html .= ' data-asciinema-fontcss="' . hsc(DOKU_BASE . 'lib/plugins/asciinema/css/cascadiacode.css') . '"';
			$html .= ' data-asciinema-fontcheck="1em &quot;CaskaydiaCove NF&quot;"';
		}

		return $html . '></div>' .
			'<noscript><a class="plugin_asciinema__link" href="' . hsc($url) . '">' .
			hsc($this->getLang('download')) . '</a></noscript>';
	}

	/**
	 * The script tag served by an asciinema server, which injects its own iframe
	 *
	 * @param array $data
	 * @return string
	 */
	protected function embedScript($data)
	{
		$opts = array_merge($this->defaultOptions(), $data['opts']);

		// only a subset of the player options is understood by the embed script
		$attributes = [
			'autoplay' => 'autoplay',
			'loop' => 'loop',
			'preload' => 'preload',
			'speed' => 'speed',
			'startAt' => 'start-at',
			'idleTimeLimit' => 'idle-time-limit',
			'poster' => 'poster',
			'cols' => 'cols',
			'rows' => 'rows',
		];

		$html = '<script async id="asciicast-' . hsc($data['cast']) . '"' .
			' src="' . hsc($data['server'] . '/a/' . $data['cast'] . '.js') . '"';

		foreach ($attributes as $option => $attribute) {
			if (!isset($opts[$option])) {
				continue;
			}
			$value = $opts[$option];
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}
			$html .= ' data-' . $attribute . '="' . hsc((string)$value) . '"';
		}

		// the embed script has no "auto/" fallback syntax and its own theme list
		if (isset($opts['theme'])) {
			$theme = preg_replace('#^auto/#', '', $opts['theme']);
			if (in_array($theme, $this->themes, true)) {
				$html .= ' data-theme="' . hsc($theme) . '"';
			}
		}

		return $html . '></script>';
	}

	/**
	 * Player options built from the plugin configuration
	 *
	 * @return array
	 */
	protected function defaultOptions()
	{
		$opts = [];

		$theme = trim($this->getConf('theme'));
		if ($theme !== '') {
			$opts['theme'] = $theme;
		}

		$fit = $this->getConf('fit');
		$opts['fit'] = ($fit === 'none') ? false : $fit;

		switch ($this->getConf('controls')) {
			case 'always':
				$opts['controls'] = true;
				break;
			case 'never':
				$opts['controls'] = false;
				break;
			default:
				$opts['controls'] = 'auto';
		}

		if ($this->getConf('autoplay')) {
			$opts['autoplay'] = true;
		}
		if ($this->getConf('loop')) {
			$opts['loop'] = true;
		}
		if ($this->getConf('preload')) {
			$opts['preload'] = true;
		}

		$speed = (float)$this->getConf('speed');
		if ($speed > 0 && $speed != 1) {
			$opts['speed'] = $speed;
		}

		$idle = (float)$this->getConf('idle_time_limit');
		if ($idle > 0) {
			$opts['idleTimeLimit'] = $idle;
		}

		$family = trim($this->getConf('font_family'));
		if ($family !== '') {
			$opts['terminalFontFamily'] = $family;
		}

		$size = trim($this->getConf('font_size'));
		if ($size !== '') {
			$opts['terminalFontSize'] = $size;
		}

		return $opts;
	}

	/**
	 * @return string
	 */
	protected function defaultWidth()
	{
		$size = strtolower(trim($this->getConf('default_size')));
		if ($size === '') {
			return '';
		}
		if (isset($this->sizes[$size])) {
			return $this->sizes[$size][0];
		}
		return $this->cssLength($size);
	}

	/**
	 * Read the option string that follows the question mark
	 *
	 * @param string $string
	 * @param array $data modified in place
	 */
	protected function parseParams($string, &$data)
	{
		foreach (preg_split('/&/', $string, -1, PREG_SPLIT_NO_EMPTY) as $token) {
			$token = trim($token);
			if ($token === '') {
				continue;
			}

			$value = null;
			if (strpos($token, '=') !== false) {
				[$token, $value] = explode('=', $token, 2);
				$value = trim($value);
			}
			$key = strtolower(trim($token));

			// a bare token may be a size, either named or explicit
			if ($value === null) {
				if (isset($this->sizes[$key])) {
					[$data['width'], $data['height']] = $this->sizes[$key];
					continue;
				}
				if (preg_match('/^(\d+[a-z%]*)(?:[x*](\d+[a-z%]*))?$/i', $key, $parts)) {
					$data['width'] = $this->cssLength($parts[1]);
					if (isset($parts[2])) {
						$data['height'] = $this->cssLength($parts[2]);
					}
					continue;
				}
			}

			$this->applyOption($key, $value, $data['opts']);
		}
	}

	/**
	 * Translate a single wiki option into an asciinema player option
	 *
	 * @param string $key
	 * @param string|null $value null for a flag written without a value
	 * @param array $opts modified in place
	 */
	protected function applyOption($key, $value, &$opts)
	{
		switch ($key) {
			case 'autoplay':
			case 'play':
				$opts['autoplay'] = $this->toBool($value);
				break;
			case 'noautoplay':
				$opts['autoplay'] = false;
				break;
			case 'loop':
				$opts['loop'] = ($value !== null && ctype_digit($value)) ? (int)$value : $this->toBool($value);
				break;
			case 'noloop':
				$opts['loop'] = false;
				break;
			case 'preload':
				$opts['preload'] = $this->toBool($value);
				break;
			case 'nopreload':
				$opts['preload'] = false;
				break;
			case 'controls':
				$opts['controls'] = ($value === 'auto') ? 'auto' : $this->toBool($value);
				break;
			case 'nocontrols':
				$opts['controls'] = false;
				break;
			case 'pause':
			case 'pauseonmarkers':
				$opts['pauseOnMarkers'] = $this->toBool($value);
				break;
			case 'keys':
			case 'keystrokes':
				$opts['keystrokeOverlay'] = $this->toBool($value);
				break;
			case 'theme':
				$theme = preg_replace('#^auto/#', '', (string)$value);
				if (in_array($theme, $this->themes, true)) {
					$opts['theme'] = (string)$value;
				}
				break;
			case 'speed':
				if ((float)$value > 0) {
					$opts['speed'] = (float)$value;
				}
				break;
			case 'start':
			case 'startat':
			case 't':
				if ($value !== null && preg_match('/^\d+(?::\d+){0,2}(?:\.\d+)?$/', $value)) {
					$opts['startAt'] = (strpos($value, ':') === false) ? (float)$value : $value;
				}
				break;
			case 'idle':
			case 'idletimelimit':
				if ((float)$value > 0) {
					$opts['idleTimeLimit'] = (float)$value;
				}
				break;
			case 'cols':
			case 'rows':
				if ((int)$value > 0) {
					$opts[$key] = (int)$value;
				}
				break;
			case 'poster':
				if ($value !== null && $value !== '') {
					$opts['poster'] = $value;
				}
				break;
			case 'fit':
				$opts['fit'] = in_array($value, ['width', 'height', 'both'], true) ? $value : false;
				break;
			case 'nofit':
				$opts['fit'] = false;
				break;
			case 'fontsize':
				if ($value !== null && $value !== '') {
					$opts['terminalFontSize'] = $value;
				}
				break;
			case 'font':
				if ($value !== null && $value !== '') {
					$opts['terminalFontFamily'] = str_replace('_', ' ', $value);
				}
				break;
			case 'lineheight':
				if ((float)$value > 0) {
					$opts['terminalLineHeight'] = (float)$value;
				}
				break;
			case 'cursor':
				if (in_array($value, ['blinking', 'steady', 'hidden'], true)) {
					$opts['cursorMode'] = $value;
				}
				break;
		}
	}

	/**
	 * A flag without a value means "on"
	 *
	 * @param string|null $value
	 * @return bool
	 */
	protected function toBool($value)
	{
		if ($value === null || $value === '') {
			return true;
		}
		return !in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
	}

	/**
	 * Keep only lengths that are safe to inject into a style attribute
	 *
	 * @param string $value
	 * @return string
	 */
	protected function cssLength($value)
	{
		if (preg_match('/^(\d+(?:\.\d+)?)(px|%|em|rem|vw|vh)?$/i', trim($value), $parts)) {
			return $parts[1] . (empty($parts[2]) ? 'px' : strtolower($parts[2]));
		}
		return '';
	}
}
