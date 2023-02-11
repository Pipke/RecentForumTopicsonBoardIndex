<?php
/*
 * @package RecentTopicsBoardIndex
 * @version 1.0
 * @Author: Pipke
 * @copyright Copyright (C) 2023, Pipke
 * @All rights reserved. 
 * @This SMF modification is subject to the BSD 2-Clause License
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT 
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT 
 * HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT 
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON 
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (!defined('SMF'))
	die('Hacking attempt...');

// Load user mod options data
function rtbi_integrate_load_member_data(&$select_columns)
{
	// Load mod db options
	$select_columns.=', mem.rtbi_user_options';	
}

// Get things started
function rtbi_integrate_load_theme() 
{
	global $context, $rtbi, $smcFunc;
	
	$rtbi['AvatarsDisplayIntegration'] = false;
	
	// Do check if the mod 'AvatarsDisplayIntegration' is installed?
	$request = $smcFunc['db_query']('', '
		SELECT version
		FROM {db_prefix}log_packages
		WHERE package_id = {string:current_package}
			AND install_state != {int:not_installed}
		LIMIT 1',
		array(
			'not_installed' => 0,
			'current_package' => 'Pipke:AvatarsDisplayIntegration',
		)
	);

	while ($row = $smcFunc['db_fetch_assoc']($request))
		$rtbi['AvatarsDisplayIntegration'] = $row['version'] ? true : false;
	
	$smcFunc['db_free_result']($request);

	// Lets go...
	RecentTopicsBoardIndex();	
}

// Warm up the engine
function RecentTopicsBoardIndex()
{
	global $context, $modSettings, $scripturl, $txt, $user_settings, $rtbi;
		
	// Only on de index please
	if (isset($context['current_action']) || isset($_REQUEST['c']) || isset($_REQUEST['board']) || isset($_REQUEST['topic'])) 
		return;	
	
	// Mod language
	loadLanguage('RecentTopicsBoardIndex');

	// If user has set there own preferences use it!
	if (isset($user_settings['rtbi_user_options']))  ## in db -> NULL
		$rtbi['options'] = unserialize($user_settings['rtbi_user_options']);
	else  { ## guest or not set any choices yet
		$rtbi['options'] = array(
			'align' => isset( $_COOKIE['rtbi_align'] ) ? $_COOKIE['rtbi_align'] : 'left',
			'per_page' => 10,
			'time' => true,
			'legend' => false,
			'topic_icons' => true,
			'first_poster_avatar' => true,
			'last_poster_avatar' => true,
			'forum_filter' => 'filter_b',
			'filter_b' => false,
			'filter_c' => false
		);
	}
		
	$rtbi_requests = array('col', 'top', 'left', 'right');	
	if (isset($_REQUEST['rtbi']) && in_array($_REQUEST['rtbi'], $rtbi_requests)) {
		$rtbi['options']['align'] = $_REQUEST['rtbi'];
		
		if ($context['user']['is_guest']) {
			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
			smf_setcookie('rtbi_align', $_REQUEST['rtbi'], time() + (10 * 365 * 24 * 60 * 60), $cookie_url[1], $cookie_url[0], false, false);	
		}
		else 	
			rtbi_update_options($context['user']['id']);			
	}
		
	$rtbi_requests = array('page', 'time', 'legend', 'icons', 'first', 'last', 'filter');	
	if ((isset($_REQUEST['rtbi']) && in_array($_REQUEST['rtbi'], $rtbi_requests)) && !$context['user']['is_guest']) {
		if ($_REQUEST['rtbi'] == 'page')
			$rtbi['options']['per_page'] = $rtbi['options']['per_page'] == 5 ? 10 : 5;
		
		if ($_REQUEST['rtbi'] == 'time')
			$rtbi['options']['time'] = $rtbi['options']['time'] ? false : true;
		
		if ($_REQUEST['rtbi'] == 'legend')
			$rtbi['options']['legend'] = $rtbi['options']['legend'] ? false : true;
		
		if ($_REQUEST['rtbi'] == 'icons')
			$rtbi['options']['topic_icons'] = $rtbi['options']['topic_icons'] ? false : true;
		
		if ($_REQUEST['rtbi'] == 'first')
			$rtbi['options']['first_poster_avatar'] = $rtbi['options']['first_poster_avatar'] ? false : true;
		
		if ($_REQUEST['rtbi'] == 'last')
			$rtbi['options']['last_poster_avatar'] = $rtbi['options']['last_poster_avatar'] ? false : true;
		
		if ($_REQUEST['rtbi'] == 'filter')
			$rtbi['options']['forum_filter'] = $rtbi['options']['forum_filter'] == 'filter_b' ? 'filter_c' : 'filter_b';
		
		rtbi_update_options($context['user']['id']);
	}
	
	// Update filter categories
	if (isset($_REQUEST['filter_c']) && empty($board) && !$context['user']['is_guest']) {
		// first check if request categories has changed
		if ($rtbi['options']['filter_c'] !== $_REQUEST['filter_c']) 
		{
			if (preg_match('/^[0-9,]+$/', $_REQUEST['filter_c'])) 
				$rtbi['options']['filter_c'] = $_REQUEST['filter_c'];
			elseif ($_REQUEST['filter_c'] !== 'clear')
				log_error('no_board', false);				
			else 				
				$rtbi['options']['filter_c'] = false;
				
			rtbi_update_options($context['user']['id']);			
		}
	}	
	// Update filter boards
	elseif (isset($_REQUEST['filter_b']) && !$context['user']['is_guest']) {
		// first check if request boards has changed
		if ($rtbi['options']['filter_b'] !== $_REQUEST['filter_b']) 
		{
			if (preg_match('/^[0-9,]+$/', $_REQUEST['filter_b']))  
				$rtbi['options']['filter_b'] = $_REQUEST['filter_b'];
			elseif ($_REQUEST['filter_b'] !== 'clear')
				log_error('no_board', false);				
			else 				
				$rtbi['options']['filter_b'] = false;
				
			rtbi_update_options($context['user']['id']);	
		}
	}
	// No request do options values
	if ($rtbi['options']['forum_filter'] == 'filter_b')
		$_REQUEST['filter_b'] = isset($rtbi['options']['filter_b']) ? $rtbi['options']['filter_b'] : false;
	else
		$_REQUEST['filter_c'] = isset($rtbi['options']['filter_c']) ? $rtbi['options']['filter_c'] : false;

	// Mark read button & Forum filter button
	if (!$context['user']['is_guest']) {		
		$context['rtbi_buttons'] = array(
			'applyfilter' => array('text' => $rtbi['options']['forum_filter'] == 'filter_b' ? 'rtbi_filter_c' : 'rtbi_filter_b', 'url' => $scripturl . '?rtbi=filter;' . $context['session_var'] . '=' . $context['session_id']),
			'markread' => array('text' => 'mark_as_read', 'image' => 'markread.png', 'custom' => 'data-confirm="' . $txt['are_sure_mark_read'] . '"', 'class' => 'you_sure', 'url' => $scripturl . '?action=markasread;sa=all;' . $context['session_var'] . '=' . $context['session_id']),
		);
	}
	
	$checkSession = $context['user']['is_guest'] ? '' : ';' . $context['session_var'] . '=' . $context['session_id'];
	
	// User menu quickbuttons
	$rtbi['quickbuttons'] = array(
		'rtbi-align-col' => array(		
			'href' => $scripturl. '?rtbi=col'.$checkSession.'" title="'.$txt['rtbi_align_col'],
			'class' => $rtbi['options']['align'] == 'col' ? 'rtbi_active' : '',
			'icon' => 'rtbi-align-top rtbi-col',
		),
		'rtbi-align-top' => array(
			'href' => $scripturl. '?rtbi=top'.$checkSession.'" title="'.$txt['rtbi_align_top'],
			'class' => $rtbi['options']['align'] == 'top' ? 'rtbi_active' : '',			
			'icon' => 'rtbi-align-top',			
		),
		'rtbi-align-left' => array(
			'href' => $scripturl. '?rtbi=left'.$checkSession.'" title="'.$txt['rtbi_align_left'],
			'class' => $rtbi['options']['align'] == 'left' ? 'rtbi_active' : '',
			'icon' => 'rtbi-align-left',
		),
		'rtbi-align-right' => array(
			'href' => $scripturl. '?rtbi=right'.$checkSession.'" title="'.$txt['rtbi_align_right'],
			'class' => $rtbi['options']['align'] == 'right' ? 'rtbi_active' : '',
			'icon' => 'rtbi-align-right',
		),
		'more' => array(
			'topics_page' => array(
				'label' => $txt['rtbi_topics_page'],
				'href' => $scripturl. '?rtbi=page' . $checkSession,
				'icon'=> $rtbi['options']['per_page'] == 5 ? 'rtbi-radio-checked' : 'rtbi-radio-check',
				'show' => !$context['user']['is_guest']
			),
			'topic_starter_time' => array(
				'label' => $txt['rtbi_topic_starter_time'],
				'href' => $scripturl. '?rtbi=time' . $checkSession,
				'icon'=> $rtbi['options']['time'] ? 'rtbi-radio-checked' : 'rtbi-radio-check',
				'show' => !$context['user']['is_guest']
			),
			'topic_legend' => array(
				'label' => $txt['rtbi_topic_legend'],
				'href' => $scripturl. '?rtbi=legend' . $checkSession,
				'icon'=> $rtbi['options']['legend'] ? 'rtbi-radio-checked' : 'rtbi-radio-check',
				'show' => !$context['user']['is_guest']
			),
			'topic_icons' => array(
				'label' => $txt['rtbi_topic_icons'],
				'href' => $scripturl. '?rtbi=icons' . $checkSession,
				'icon'=> $rtbi['options']['topic_icons'] ? 'rtbi-radio-checked' : 'rtbi-radio-check',
				'show' => !$context['user']['is_guest']
			),
			'first_poster_avatar' => array(
				'label' => $txt['rtbi_first_poster_avatar'],
				'href' => $scripturl. '?rtbi=first' . $checkSession,
				'icon'=> $rtbi['options']['first_poster_avatar'] ? 'rtbi-radio-checked' : 'rtbi-radio-check',
				'show' => !$context['user']['is_guest'] && $rtbi['AvatarsDisplayIntegration']
			),
			'last_poster_avatar' => array(
				'label' => $txt['rtbi_last_poster_avatar'],
				'href' => $scripturl. '?rtbi=last' . $checkSession,
				'icon'=> $rtbi['options']['last_poster_avatar'] ? 'rtbi-radio-checked' : 'rtbi-radio-check',
				'show' => !$context['user']['is_guest'] && $rtbi['AvatarsDisplayIntegration']
			),
		),
	);
	
	// Change the more... text to icon @quickbuttons menu
	$txt['post_options'] = '<i class="rtbi-menu-more"></i>';	
	
	// Load what it's all about...
	rtbi_mainbasic();
	
	// Extra template layers
	$context['template_layers'][] = 'rtbi';
	
	// Fire it up ;)
	loadTemplate('RecentTopicsBoardIndex', 'RecentTopicsBoardIndex'); #template, css	
}

//Find the ten most recent topics.
function rtbi_mainbasic() 
{	
	global $txt, $scripturl, $user_info, $context, $modSettings, $board, $smcFunc, $cache_enable, $settings, $options, $rtbi;

	// Setup the default topic icons... for checking they exist and the like ;)
	$context['icon_sources'] = array();
	foreach ($context['stable_icons'] as $icon)
		$context['icon_sources'][$icon] = 'images_url';
	
	$context['is_redirect'] = false;

	if (isset($_REQUEST['start']) && $_REQUEST['start'] > 95)
		$_REQUEST['start'] = 95;

	$_REQUEST['start'] = (int) $_REQUEST['start'];

	$query_parameters = array();
	if (!empty($_REQUEST['filter_c']) && empty($board))
	{
		$_REQUEST['filter_c'] = explode(',', $_REQUEST['filter_c']);
		foreach ($_REQUEST['filter_c'] as $i => $c)
			$_REQUEST['filter_c'][$i] = (int) $c;

		$request = $smcFunc['db_query']('', '
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat IN ({array_int:id_cat})',
			array(
				'id_cat' => $_REQUEST['filter_c'],
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$name[] = $row['name'];
		}
		$smcFunc['db_free_result']($request);

		if (empty($name))
			redirectexit('c=clear;'.$context['session_var'] . '=' . $context['session_id'] . '');
			

		$context['linktree'][] = array(
			'name' => $txt['rtbi_forum filter category'] .implode(', ', $name),  //hardcoded text todo
		);

		$recycling = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']);

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_topics
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:category_list})
				AND b.redirect = {string:empty}' . ($recycling ? '
				AND b.id_board != {int:recycle_board}' : '') . '
				AND {query_wanna_see_board}',
			array(
				'category_list' => $_REQUEST['filter_c'],
				'empty' => '',
				'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
			)
		);
		$total_cat_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$boards[] = $row['id_board'];
			$total_cat_posts += $row['num_topics'];
		}
		$smcFunc['db_free_result']($request);
		
		// Clear category filter, this will happen if invalid or no more access to db settings filtered boards
		if (empty($boards)) 
			redirectexit('c=clear;'.$context['session_var'] . '=' . $context['session_id'] . '');

		$query_this_board = 'm.id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;

		// If this category has a significant number of posts in it...
		if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 400 - $_REQUEST['start'] * 7);
		}

		$context['page_index'] = constructPageIndex($scripturl . '?c=' . implode(',', $_REQUEST['filter_c']), $_REQUEST['start'], min(100, $total_cat_posts), $rtbi['options']['per_page'], false);
	}
	elseif (!empty($_REQUEST['filter_b']))
	{
		$_REQUEST['filter_b'] = explode(',', $_REQUEST['filter_b']);
		foreach ($_REQUEST['filter_b'] as $i => $b)
			$_REQUEST['filter_b'][$i] = (int) $b;

		$request = $smcFunc['db_query']('', '
			SELECT b.id_board, b.num_topics, b.name
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND b.redirect = {string:empty}
				AND {query_see_board}
			LIMIT {int:limit}',
			array(
				'board_list' => $_REQUEST['filter_b'],
				'limit' => count($_REQUEST['filter_b']),
				'empty' => '',
			)
		);
		$total_posts = 0;
		$boards = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$boards[] = $row['id_board'];
			$total_posts += $row['num_topics'];
			$name[] = $row['name'];
		}
		$smcFunc['db_free_result']($request);

		// Clear board filter, this will happen if invalid or no more access to db settings filtered boards
		if (empty($boards)) 
			redirectexit('boards=clear;'.$context['session_var'] . '=' . $context['session_id'] . '');
			
		$context['linktree'][] = array(
			'name' => $txt['rtbi_forum_filter_board'] .implode(', ', $name), 
		);

		$query_this_board = 'm.id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;

		// If these boards have a significant number of posts in them...
		if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 500 - $_REQUEST['start'] * 9);
		}

		$context['page_index'] = constructPageIndex($scripturl . '?boards=' . implode(',', $_REQUEST['filter_b']), $_REQUEST['start'], min(100, $total_posts), $rtbi['options']['per_page'], false);
	}
	elseif (!empty($board))
	{
		$request = $smcFunc['db_query']('', '
			SELECT num_topics, redirect
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);
		list ($total_posts, $redirect) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);

		// If this is a redirection board, don't bother counting topics here...
		if ($redirect != '')
		{
			$total_posts = 0;
			$context['is_redirect'] = true;
		}

		$query_this_board = 'm.id_board = {int:board}';
		$query_parameters['board'] = $board;

		// If this board has a significant number of posts in it...
		if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / $rtbi['options']['per_page'])
		{
			$query_this_board .= '
					AND m.id_msg >= {int:max_id_msg}';
			$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 600 - $_REQUEST['start'] * $rtbi['options']['per_page']);
		}

		$context['page_index'] = constructPageIndex($scripturl . '?board=' . $board . '.%1$d', $_REQUEST['start'], min(100, $total_posts), $rtbi['options']['per_page'], true);
	}
	else
	{
		$query_this_board = '{query_wanna_see_message_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
					AND m.id_board != {int:recycle_board}' : '') . '
					AND m.id_msg >= {int:max_id_msg}';
		$query_parameters['max_id_msg'] = max(0, $modSettings['maxMsgID'] - 100 - $_REQUEST['start'] * 6);
		$query_parameters['recycle_board'] = $modSettings['recycle_board'];

		$query_these_boards = '{query_wanna_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
					AND b.id_board != {int:recycle_board}' : '');
		$query_these_boards_params = $query_parameters;
		unset($query_these_boards_params['max_id_msg']);

		$get_num_topics = $smcFunc['db_query']('', '
			SELECT COALESCE(SUM(b.num_topics), 0)
			FROM {db_prefix}boards AS b
			WHERE ' . $query_these_boards . '
				AND b.redirect = {string:empty}',
			array_merge($query_these_boards_params, array('empty' => ''))
		);

		list($db_num_topics) = $smcFunc['db_fetch_row']($get_num_topics);
		$num_topics = min(100, $db_num_topics);

		$smcFunc['db_free_result']($get_num_topics);

		$context['page_index'] = constructPageIndex($scripturl . '?action=forum', $_REQUEST['start'], $num_topics, $rtbi['options']['per_page'], false);
	}

	// If you selected a redirection board, don't try getting posts for it...
	if ($context['is_redirect'])
		$messages = 0;
	
	$key = 'recent-' . $user_info['id'] . '-' . md5($smcFunc['json_encode'](array_diff_key($query_parameters, array('max_id_msg' => 0)))) . '-' . (int) $_REQUEST['start'];
	if (!$context['is_redirect'] && (empty($cache_enable) || ($messages = cache_get_data($key, 120)) == null))
	{
		$done = false;
		while (!$done)
		{
			// Find the 10 or 5 most recent messages they can *view*.
			// @todo SLOW This query is really slow still, probably?
			$request = $smcFunc['db_query']('', '
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
					INNER JOIN {db_prefix}topics AS t ON (t.id_last_msg = m.id_msg)
					WHERE ' . $query_this_board . '
					AND m.approved = {int:is_approved}
				ORDER BY m.id_msg DESC
				LIMIT {int:offset}, {int:limit}',
				array_merge($query_parameters, array(
					'is_approved' => 1,
					'offset' => $_REQUEST['start'],
					'limit' => $rtbi['options']['per_page'],
				))
			);
			// If we don't have 10 or 5 results, try again with an unoptimized version covering all rows, and cache the result.
			if (isset($query_parameters['max_id_msg']) && $smcFunc['db_num_rows']($request) < $rtbi['options']['per_page'])
			{
				$smcFunc['db_free_result']($request);
				$query_this_board = str_replace('AND m.id_msg >= {int:max_id_msg}', '', $query_this_board);
				$cache_results = true;
				unset($query_parameters['max_id_msg']);
			}
			else
				$done = true;
		}
		$messages = array();
		$topics = array();
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			$messages[] = $row['id_msg'];
		}
		$smcFunc['db_free_result']($request);
		if (!empty($cache_results))
			cache_put_data($key, $messages, 120);
	}
	// Nothing here... Or at least, nothing you can see...
	if (empty($messages))
	{
		$context['topics'] = array();
		return;
	}

	// Are we doing avatar data query?
	$query_avatars_data = $rtbi['AvatarsDisplayIntegration'] && ($rtbi['options']['last_poster_avatar'] || $rtbi['options']['first_poster_avatar']) ? true : false;
	
	// Get all the most recent posts
	$request = $smcFunc['db_query']('', '
        SELECT
            m.id_msg, m.id_msg_modified, m2.subject AS first_subject, m.smileys_enabled, m.poster_time AS last_poster_time , m.body as last_body, m.id_topic, t.id_board, b.id_cat, 
            b.name AS bname, c.name AS cname, t.num_replies, t.num_views, m.id_member, m2.id_member AS id_first_member,' . ($user_info['is_guest'] ? '1 AS is_read, 0 AS new_from' : '
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) >= m.id_msg_modified AS is_read,
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from') . ',
            m2.poster_time AS first_poster_time, m2.icon AS first_icon, m.icon AS last_icon, SUBSTRING(m2.body, 1, 385) AS first_body,
            t.approved, t.unapproved_posts, t.id_poll, t.is_sticky, t.locked, m.smileys_enabled AS last_smileys, m2.smileys_enabled AS first_smileys,
            IFNULL(mem2.real_name, m2.poster_name) AS first_poster_name, t.id_first_msg,' . (!empty($query_avatars_data) ? ' mem.avatar, mem.email_address, mem2.avatar AS first_member_avatar, mem2.email_address AS first_member_mail, COALESCE(af.id_attach, 0) AS first_member_id_attach, af.filename AS first_member_filename, af.attachment_type AS first_member_attach_type, COALESCE(al.id_attach, 0) AS last_member_id_attach, al.filename AS last_member_filename, al.attachment_type AS last_member_attach_type,' : '') . '
            IFNULL(mem.real_name, m.poster_name) AS poster_name, t.id_last_msg, m.subject AS last_subject
	    FROM {db_prefix}messages AS m
            INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
            INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
            INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
            INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_first_msg)
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
            LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)' . (!empty($query_avatars_data) ? '
			LEFT JOIN {db_prefix}attachments AS af ON (af.id_member = m2.id_member)
			LEFT JOIN {db_prefix}attachments AS al ON (al.id_member = m.id_member)' : ''). '' . (!$user_info['is_guest'] ? '
            LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = m.id_topic AND lt.id_member = {int:current_member})
            LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = m.id_board AND lmr.id_member = {int:current_member})' : '') . '
        WHERE m.id_msg IN ({array_int:message_list})
        ORDER BY m.id_msg DESC
        LIMIT ' . count($messages),
		array(
			'message_list' => $messages,
			'current_member' => $user_info['id'],
		)
	);
	$counter = $_REQUEST['start'] + 1;
	$context['topics'] = array();
	$topic_ids = array();
	$board_ids = array('own' => array(), 'any' => array());
	
	$recycle_board = !empty($modSettings['recycle_enable']) && !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0;

	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
			continue;

		$topic_ids[] = $row['id_topic'];
		
		if (!empty($modSettings['preview_characters']))
		{
			// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br>' => '&#10;')));
			if ($smcFunc['strlen']($row['first_body']) > 128)
				$row['first_body'] = $smcFunc['substr']($row['first_body'], 0, 128) . '...';
			$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br>' => '&#10;')));
			if ($smcFunc['strlen']($row['last_body']) > 128)
				$row['last_body'] = $smcFunc['substr']($row['last_body'], 0, 128) . '...';

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}
		
		// Decide how many pages the topic should have.
		$topic_length = $row['num_replies'] + 1;
		$messages_per_page = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
		if ($topic_length > $messages_per_page)
		{
			$start = -1;
			$pages = constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $topic_length, $messages_per_page, true, false);

			// If we can use all, show all.
			if (!empty($modSettings['enableAllMessages']) && $topic_length < $modSettings['enableAllMessages'])
				$pages .= sprintf(strtr($settings['page_index']['page'], array('{URL}' => $scripturl . '?topic=' . $row['id_topic'] . '.0;all')), '', $txt['all']);
		}

		else
			$pages = '';
		
		// We need to check the topic icons exist... you can never be too sure!
		if (!empty($modSettings['messageIconChecks_enable']))
		{
			// First icon first... as you'd expect.
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.png') ? 'images_url' : 'default_images_url';
			// Last icon... last... duh.
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.png') ? 'images_url' : 'default_images_url';
		}
		else
		{
			if (!isset($context['icon_sources'][$row['first_icon']]))
				$context['icon_sources'][$row['first_icon']] = 'images_url';
			if (!isset($context['icon_sources'][$row['last_icon']]))
				$context['icon_sources'][$row['last_icon']] = 'images_url';
		}

		// Force the recycling icon if appropriate
		if ($recycle_board == $row['id_board'])
		{
			$row['first_icon'] = 'recycled';
			$row['last_icon'] = 'recycled';
		}
		
		// Reference the main color class.
		$colorClass = 'windowbg';

		// Sticky topics should get a different color, too.
		if ($row['is_sticky'])
			$colorClass .= ' sticky';

		// Locked topics get special treatment as well.
		if ($row['locked'])
			$colorClass .= ' locked';
		
		// And build the array.
		$context['topics'][$row['id_topic']] = array(
		'id_msg' => $row['id_msg'],
        'category' => array(
            'id' => $row['id_cat'],
            'name' => $row['cname'],
            'href' => $scripturl . '#c' . $row['id_cat'],
            'link' => '<a href="' . $scripturl . '#c' . $row['id_cat'] . '">' . $row['cname'] . '</a>'
        ),
        'board' => array(
            'id' => $row['id_board'],
            'name' => $row['bname'],
            'href' => $scripturl . '?board=' . $row['id_board'] . '.0',
            'link' => '<a href="' . $scripturl . '?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
        ),
        'topic' => $row['id_topic'],
        'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
		'pages' => $pages,
        'views' => comma_format($row['num_views']),
        'replies' => comma_format($row['num_replies']),
		'first_post' => array(
			'id' => $row['id_first_msg'],
			'member' => array(
				'name' => $row['first_poster_name'],
				'id' => $row['id_first_member'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_first_member'],
				'link' => !empty($row['id_first_member']) ? '<a class="preview" href="' . $scripturl . '?action=profile;u=' . $row['id_first_member'] . '" title="' . sprintf($txt['view_profile_of_username'], $row['first_poster_name']) . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
			),
			'time' => $rtbi['options']['time'] ? timeformat($row['first_poster_time']) : '',
			'timestamp' => $row['first_poster_time'],
			'subject' => $row['first_subject'],
			'preview' => $row['first_body'],
			'icon' => $row['first_icon'],
			'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.png',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;topicseen">' . $row['first_subject'] . '</a>'
		),
		'last_post' => array(  // was 'poster'
			'id' => $row['id_last_msg'],
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'time' => timeformat($row['last_poster_time']),
			'timestamp' => $row['last_poster_time'],
			'subject' => $row['last_subject'],
			'preview' => $row['last_body'],
			'icon' => $row['last_icon'],
			'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.png',
			'href' => $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . ';topicseen#msg' . $row['id_last_msg'] . '" rel="nofollow">' . $row['last_subject'] . '</a>'
		),
        'new' => $row['new_from'] <= $row['id_msg_modified'],
        'new_from' => $row['id_msg'],
        'newtime' => $row['new_from'],
		'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . ';topicseen#new',
        'id_msg_modified' => $row['id_msg_modified'],
        'is_sticky' => !empty($row['is_sticky']),
        'is_locked' => !empty($row['locked']),
		'css_class' => $colorClass,
        'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
        'icon' => $row['first_icon'],
		'is_posted_in' => false
		);
			
		$context['topics'][$row['id_topic']]['first_post']['started_by'] = sprintf($txt['rtbi_topic_by'], $context['topics'][$row['id_topic']]['first_post']['member']['link'], $context['topics'][$row['id_topic']]['first_post']['time'], $context['topics'][$row['id_topic']]['board']['link']);	
		
		if (!empty($query_avatars_data))
		{
			// Last post member avatar
			$context['topics'][$row['id_topic']]['last_post']['member']['avatar'] = set_avatar_data(array(
				'avatar' => $row['avatar'],
				'email' => $row['email_address'],
				'filename' => !empty($row['last_member_filename']) ? $row['last_member_filename'] : '',
			));

			// First post member avatar
			$context['topics'][$row['id_topic']]['first_post']['member']['avatar'] = set_avatar_data(array(
				'avatar' => $row['first_member_avatar'],
				'email' => $row['first_member_mail'],
				'filename' => !empty($row['first_member_filename']) ? $row['first_member_filename'] : '',
			));
		}
	}
	$smcFunc['db_free_result']($request);
	
	// Participation, has the user posted in topics
	if (!empty($modSettings['enableParticipation']) && !empty($topic_ids))
	{
		$result = $smcFunc['db_query']('', '
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:current_member}
			GROUP BY id_topic
			LIMIT {int:limit}',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topic_ids,
				'limit' => count($topic_ids),
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			if (empty($context['topics'][$row['id_topic']]['is_posted_in']))
				$context['topics'][$row['id_topic']]['is_posted_in'] = true;
		}
		$smcFunc['db_free_result']($result);
	}
}

function rtbi_update_options($id)
{
	global $smcFunc, $rtbi;
	
	checkSession('request');
	
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}members
		SET rtbi_user_options = {string:options}
		WHERE id_member = {int:id_member}',
		array(
			'id_member' => $id,
			'options' => serialize($rtbi['options']),
		)
	);
}

function rtbi_filter_url($filter, $num)
{
	global $txt, $context, $scripturl, $rtbi;
	
	if ($context['user']['is_guest'])
		return;
	
	if (isset($rtbi['options'][$filter]))
		$ids = explode(',', $rtbi['options'][$filter]);
	else
		$ids = array();
	
	if (($key = array_search($num, $ids)) !== false) {
		unset($ids[$key]);
		$icon = 'checked';
	}
	else {
		$ids[] = $num;
		$icon = 'check';
	}

	//Make nice array order
	asort($ids);
	
	return '<a href="' . $scripturl . '?'.$filter.'=' .(!empty($ids) ? implode(',', $ids) : 'clear'). ';'.$context['session_var'] . '=' . $context['session_id'] . '"><i class="rtbi-'.$icon.'" title="'.$txt['rtbi_forum_filter'].'"></i></a>';

}
function rtbi_thousands_format($posts) {

    if( $posts > 1000 ) {

        $x = round($posts);
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = array('k', 'm', 'b', 't');
        $x_count_parts = count($x_array) - 1;
        $x_display = $x;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];
        
        return $x_display;
    }

    return $posts;
}

// Show the hard work
function rtbi_integrate_credits()
{
	global $context;
	
	$context['copyrights']['mods'][] = 'Recent Topics on Board Index v1.0 | &copy 2023 by <a href="http://www.simplemachines.org/community/index.php?action=profile;u=314795">Pipke</a>';	
}

?>