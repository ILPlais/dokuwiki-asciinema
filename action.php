<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;

/**
 * DokuWiki Plugin asciinema (Action Component)
 *
 * Appends the plugin's own mime.conf to the mime configuration cascade so that
 * .cast recordings become an allowed media type. This has to happen before the
 * first call to getMimeTypes(), which caches its result statically. Action
 * plugins are registered while DokuWiki initialises, which is early enough.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Lionel PLAIS <lionel.plais@ilp-web.net>
 */
class action_plugin_asciinema extends ActionPlugin
{
	/** @inheritDoc */
	public function register(EventHandler $controller)
	{
		$this->registerMimeType();
	}

	/**
	 * Add "cast" to the uploadable and servable extensions
	 */
	public function registerMimeType()
	{
		if (!$this->getConf('register_mime')) {
			return;
		}

		global $config_cascade;

		if (!isset($config_cascade['mime']['local']) || !is_array($config_cascade['mime']['local'])) {
			$config_cascade['mime']['local'] = [];
		}

		// prepended so that a wiki wide conf/mime.local.conf still has the last word
		$file = __DIR__ . '/conf/mime.conf';
		if (!in_array($file, $config_cascade['mime']['local'], true)) {
			array_unshift($config_cascade['mime']['local'], $file);
		}
	}
}
