<?php

/**
 * Instagram API Class
 *
 * @link      https://github.com/locomotivemtl/instagram
 * @copyright Copyright © 2016 Locomotive
 * @license   https://github.com/locomotivemtl/instagram/blob/master/LICENSE (MIT License)
 */

namespace Locomotive\Instagram;

use \DateTime;
use \InstagramExcepton;

/**
 * Instagram API Class
 *
 * @link https://instagram.com/developer/           API Documentation
 * @link https://github.com/locomotivemtl/instagram Class Documentation
 */
class Instagram
{
	private $social_feed_max_count;
	private $client_id;
	private $client_secret;
	private $user_id;
	private $auth_token;
	private $redirect_url;
	private $instagram_url;
	private $instagram_api_url;

	/**
	 * Build a new Instagram object
	 *
	 * ```
	 * https://www.instagram.com/oauth/authorize/?client_id={client_id}&redirect_uri={redirect_url}&response_type=token&scope=public_content
	 * ```
	 *
	 * @param array $options
	 */
	function __construct(array $options = [])
	{
		if (
			! isset($options['client_id'])     ||
			! isset($options['client_secret']) ||
			! isset($options['user_id'])       ||
			! isset($options['auth_token'])
		) {
			throw new InstagramExcepton('Client ID, Client Secret, User ID, and Auth Token required.');
		}

		$this->client_id             = $options['client_id'];
		$this->client_secret         = $options['client_secret'];
		$this->user_id               = $options['user_id'];
		$this->auth_token            = $options['auth_token'];

		$this->redirect_url          = ( isset($options['redirect_url'])      ? $options['redirect_url']      : '' );
		$this->instagram_url         = ( isset($options['instagram_url'])     ? $options['instagram_url']     : 'https://api.instagram.com/v1/' );
		$this->instagram_api_url     = ( isset($options['instagram_api_url']) ? $options['instagram_api_url'] : 'https://www.instagram.com/' );
		$this->social_feed_max_count = ( isset($options['max_count'])         ? $options['max_count']         : '0' );
	}

	/**
	 * Takes Instagram API data and formats for our usage
	 *
	 * @param   array  $options
	 * @return  array
	 */
	function user_feed(array $options = [])
	{
		$hashtag = $options['hashtag'];
		// 0 will not set count param, and fetch the default 20
		$count = isset($options['count']) ? $options['count'] : 0;
		$media_array = [];

		$api_url = $this->instagram_url. 'users/' . $this->user_id . '/media/recent/?count=' . $this->social_feed_max_count . '&access_token=' . $this->auth_token;
		$data = $this->url_extractor($api_url);
		$data = json_decode($data)->data;

		foreach ($data as $media) {
			$media_array[] = [
				'media_id'               => $media->id,
				'media_link'             => $media->link,
				'media_date'             => $this->format_social_date($media->created_at),
				'media_formatted_date'   => $this->format_social_interval((int)$media->created_time),
				'media_author'           => $media->user->full_name,
				'media_screen_name'      => $media->user->username,
				'media_screen_name_link' => $this->instagram_url . $media->user->username,
				'media_caption'          => $media->caption->text,
				'media_image'            => $media->images->standard_resolution->url
			];
		}

		return $media_array;
	}

	/**
	 * @param   array  $options
	 * @return  array
	 */
	function hashtag_feed(array $options = [])
	{
		$hashtag = $options['hashtag'];
		// 0 will not set count param, and fetch the default 20
		$count = isset($options['count']) ? $options['count'] : 0;
		$media_array = [];

		if (!empty($hashtag)) {
			$api_url = $this->instagram_url. 'tags/' . $hashtag . '/media/recent?access_token=' . $this->auth_token . ($count ? '&count=' . $count : '');
			$data = $this->url_extractor($api_url);
			$data = json_decode($data)->data;

			foreach ($data as $media) {
				$media_array[] = [
					'media_id'               => $media->id,
					'media_link'             => $media->link,
					'media_date'             => $this->format_social_date($media->created_time),
					'media_formatted_date'   => $this->format_social_interval((int)$media->created_time),
					'media_author'           => $media->user->full_name,
					'media_screen_name'      => $media->user->username,
					'media_screen_name_link' => $this->instagram_url . $media->user->username,
					'media_caption'          => $media->caption->text,
					'media_image'            => $media->images->standard_resolution->url
				];
			}
		}

		return $media_array;
	}

	/**
	 * Basic cURL request to API with headers
	 *
	 * @param  string  $url      API URL request
	 * @param  array   $headers  Array of HTTP headers
	 * @return string            JSON data
	 */

	private function url_extractor($url, array $headers = [])
	{
		$connection = curl_init();
		curl_setopt($connection, CURLOPT_URL, $url);
		curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($connection, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
		$data = curl_exec($connection);
		curl_close($connection);

		return $data;
	}

	/**
	 * Return human readable time interval
	 *
	 * @link   http://stackoverflow.com/a/2916189
	 * @param  string|integer  $time
	 * @return string
	 */
	private function format_social_interval($old_date_string)
	{
		if (is_numeric($old_date_string)) {
			$old_date = new DateTime();
			$old_date->setTimestamp($old_date_string);
		} else {
			$old_date = new DateTime($old_date_string);
		}

		$now_date = new DateTime('now');
		$diff = $now_date->getTimestamp() - $old_date->getTimestamp();
		$diff = ($diff < 1) ? 1 : $diff;

		$tokens = [
			31536000 => 'année',
			2592000  => 'mois',
			604800   => 'semaine',
			86400    => 'jour',
			3600     => 'heure',
			60       => 'minute',
			1        => 'seconde'
		];

		foreach ($tokens as $unit => $text) {
			if ($diff < $unit) {
				continue;
			}

			$numberOfUnits = floor($diff / $unit);

			if ($unit >= 86400) {
				return $old_date->format('M d');
			} else {
				return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
			}
		}
	}

	/**
	 * Return human readable date
	 *
	 * @link   http://stackoverflow.com/a/2916189
	 * @param  string  $time
	 * @return string
	 */
	private function format_social_date($time_string)
	{
		if (is_numeric($time_string)) {
			$date = new DateTime();
			$date->setTimestamp($time_string);
		} else {
			$date = new DateTime($time_string);
		}

		return $date->format('Y-m-d H:i');
	}
}
