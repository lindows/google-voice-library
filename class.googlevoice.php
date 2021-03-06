<?php

/**
 * Google Voice Library.
 *
 * LICENSE:
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * You may not use this work except in compliance with the License.
 * You may obtain a copy of the License in the LICENSE file, or at:
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * @copyright    Copyright (c) 2012 Matthew Gates.
 * @license      http://www.apache.org/licenses/LICENSE-2.0
 * @link         https://github.com/Geczy/google-voice-library
 * @author       Matthew Gates <info@mgates.me>
 * @package      Google Voice
 */


namespace Geczy\Voice;
class GoogleVoiceLibrary
{

	/**
	 * API request URLs to Google Voice.
	 *
	 * @var array
	 *
	 * @access private
	 */
	private $urls = array(
		// Authenticate
		'login' => 'https://www.google.com/accounts/ClientLogin',

		// Requests
		'search' => 'https://www.google.com/voice/b/0/inbox/search/',
		'get'    => 'https://www.google.com/voice/b/0/request/messages/',
		'send'   => 'https://www.google.com/voice/b/0/sms/send/',

		// Actions
		'mark_read' => 'https://www.google.com/voice/b/0/inbox/mark/',
		'archive'   => 'https://www.google.com/voice/b/0/inbox/archiveMessages/',
		'delete'    => 'https://www.google.com/voice/b/0/inbox/deleteMessages/',
	);

	/**
	 * Username and password to the Google Voice account.
	 *
	 * @param string  $user
	 * @param string  $pass
	 */
	public function __construct( $user, $pass )
	{
		// Start the session.
		if ( !isset( $_SESSION ) ) session_start();

		// Preform authentication
		$this->set_login_auth( $user, $pass );
	}


	/**
	 * Retrieve a page using CURL.
	 *
	 * @param string  $url
	 * @param array   $params (optional)
	 * @param unknown $post   (optional)
	 * @return string
	 */
	private function get_page( $url, $params = array(), $post = true )
	{
		$login_auth = $this->auth ? $this->auth : '';

		// GET request rather than POST
		if ( !$post )
			$url = $url . '?' . http_build_query( $params );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Authorization: GoogleLogin {$login_auth}", 'User-Agent: Mozilla/5.0' ) );

		if ( $params && $post ) {
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $params ) );
		}

		$response = curl_exec( $ch );
		curl_close( $ch );

		return $response;
	}


	/**
	 * Authenticate the Google Voice account.
	 *
	 * @param string  $user
	 * @param string  $pass
	 * @return string
	 */
	private function get_login_auth( $user, $pass )
	{
		// Auth has already been set.
		if ( !empty( $_SESSION['Geczy']['login_auth'] ) )
			return $_SESSION['Geczy']['login_auth'];

		$params = array(
			'accountType' => 'GOOGLE',
			'Email'       => $user,
			'Passwd'      => $pass,
			'service'     => 'grandcentral',
			'source'      => 'Geczy-Google-Voice-Library',
		);

		$results = $this->get_page( $this->urls['login'], $params );
		$auth = $results ? strstr( trim( $results ), 'Auth=' ) : false;

		return $auth;
	}


	/**
	 * Set the authentication token to a session.
	 *
	 * @param string  $user
	 * @param string  $pass
	 * @return string
	 */
	private function set_login_auth( $user, $pass )
	{
		$auth = $this->get_login_auth( $user, $pass );

		if ( $auth ) {
			$_SESSION['Geczy']['login_auth'] = $auth;
			$this->auth = $auth;
		}

		return $auth;
	}


	/**
	 * String some Google Voice requests require.
	 *
	 * @return string
	 */
	public function get_rnrse()
	{
		if ( !empty( $_SESSION['Geczy']['rnr_se'] ) )
			return $_SESSION['Geczy']['rnr_se'];

		$result = json_decode( $this->get_page( $this->urls['get'] ) );
		$_SESSION['Geczy']['rnr_se'] = $result->r;

		return $result->r;
	}


	/**
	 * Search for a message.
	 *
	 * @param string  $query
	 * @return object
	 */
	public function search( $query, $page = 1 )
	{
		$params = array(
			'q'    => $query,
			'page' => 'p' . $page,
		);

		$result = $this->get_page( $this->urls['search'], $params, false );
		$json = simplexml_load_string( $result );

		return json_decode( (string) $json->json );
	}


	/**
	 * Delete a message.
	 *
	 * @param string  $id
	 * @return object
	 */
	public function delete( $id )
	{
		$params = array(
			'messages' => $id,
			'trash'    => 1,
			'_rnr_se'  => $this->get_rnrse(),
		);

		return json_decode( $this->get_page( $this->urls['delete'], $params ) );
	}


	/**
	 * Archive a message.
	 *
	 * @param string  $id
	 * @return object
	 */
	public function archive( $id )
	{
		$params = array(
			'messages' => $id,
			'archive'  => 1,
			'_rnr_se'  => $this->get_rnrse(),
		);

		return json_decode( $this->get_page( $this->urls['archive'], $params ) );
	}


	/**
	 * Mark a message as read.
	 *
	 * @param string  $id
	 * @return object
	 */
	public function mark_read( $id )
	{
		$params = array(
			'messages' => $id,
			'read'     => 1,
			'_rnr_se'  => $this->get_rnrse(),
		);

		return json_decode( $this->get_page( $this->urls['mark_read'], $params ) );
	}


	/**
	 * Send a text to a number.
	 *
	 * @param string  $to
	 * @param string  $msg
	 * @param string  $id  (optional)
	 * @return object
	 */
	public function send_text( $to, $msg, $id = '' )
	{
		$params = array(
			'conversationId' => $id,
			'phoneNumber'    => $to,
			'text'           => $msg,
			'_rnr_se'        => $this->get_rnrse(),
		);

		return json_decode( $this->get_page( $this->urls['send'], $params ) );
	}


	/**
	 * Retrieve the current inbox.
	 *
	 * @param array   $params (optional)
	 * @return array
	 */
	public function get_inbox( $params = array() )
	{
		$defaults = array(
			'history' => false,
			'onlyNew' => true,
			'page'    => 1,
		);

		$params = array_merge( $defaults, $params );

		$json = json_decode( $this->get_page( $this->urls['get'].'?page='.$params['page'] ) );
		$results = $this->parse_texts( $json, $params );

		return $results;
	}


	/**
	 * Helper for formatting the inbox.
	 *
	 * @param object  $data
	 * @param array   $params
	 * @return array
	 */
	private function parse_texts( $data, $params )
	{
		$contacts = $data->contacts->contactPhoneMap;

		$results = array(
			'unread' => $data->unreadCounts->sms,
			'total'  => $data->totalSize,
		);

		foreach ( $data->messageList as $thread ) {

			/* This message is already read, so skip */
			if ( $params['onlyNew'] && $thread->isRead )
				continue;

			/* Extract just the information that's useful. */
			$number = $thread->phoneNumber;
			$results['texts'][$thread->id] = array(
				'from'   => $contacts->$number->name,
				'number' => $thread->displayNumber,
				'date'   => $thread->displayStartDateTime,
				'text'   => $thread->children[count( $thread->children )-1]->message,
			);

			if ( $params['history'] ) {
				foreach ( $thread->children as $child ) {
					$results['texts'][$thread->id]['history'][] = array(
						'from'    => $child->type == 11 ? 'Me' : $results['texts'][$thread->id]['from'],
						'time'    => $child->displayStartDateTime,
						'message' => $child->message,
					);
				}
			}

		}

		return $results;
	}


}
