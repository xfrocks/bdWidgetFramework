<?php
class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Threads',
			'options' => array(
				'type' => XenForo_Input::STRING,
				'cutoff' => XenForo_Input::UINT,
				'forums' => XenForo_Input::ARRAY_SIMPLE,
				'limit' => XenForo_Input::UINT,
			),
			'useCache' => true,
			'useUserCache' => true,
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_threads';
	}
	
	protected function _renderOptions(XenForo_Template_Abstract $template) {
		$nodes = WidgetFramework_Core::getInstance()->getModelFromCache('XenForo_Model_Node')->getAllNodes();
		$forums = array();
		$params = $template->getParams();
		$options = $params['options'];
		$value = empty($options['forums'])?array():$options['forums'];
		
		foreach ($nodes as $node) {
			$forums[] = array(
				'value' => $node['node_id'],
				'label' => str_repeat('--', $node['depth']) . ' ' . $node['title'],
				'selected' => in_array($node['node_id'], $value),
			);
		}
		
		$template->setParam('forums', $forums);
	}
	
	protected function _validateOptionValue($optionKey, &$optionValue) {
		if ('type' == $optionKey) {
			if (!in_array($optionValue, array('new', 'recent', 'popular', 'polls'))) {
				throw new XenForo_Exception(new XenForo_Phrase('wf_widget_threads_invalid_type'), true);
			}
		} elseif ('limit' == $optionKey) {
			if (empty($optionValue)) $optionValue = 5;
		} elseif ('cutoff' == $optionKey) {
			if (empty($optionValue)) $optionValue = 5;
		}
		
		return true;
	}
	
	protected function _getRenderTemplate(array $widget, $positionCode, array $params) {
		return 'wf_widget_threads';
	}
	
	protected function _render(array $widget, $positionCode, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$core = WidgetFramework_Core::getInstance();
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');
		$nodeModel = $core->getModelFromCache('XenForo_Model_Node');
		$visitor = XenForo_Visitor::getInstance();

		if (empty($widget['options']['forums'])) {
			if (empty($GLOBALS['WidgetFramework_viewableNodeList'])) {
				$GLOBALS['WidgetFramework_viewableNodeList'] = $nodeModel->getViewableNodeList();
			}

			$forumIds = array_keys($GLOBALS['WidgetFramework_viewableNodeList']);
		} else {
			$forumIds = $widget['options']['forums'];
		}
		
		$conditions = array(
			'forum_ids' => $forumIds,
			'deleted' => $visitor->isSuperAdmin(),
			'moderated' => $visitor->isSuperAdmin(),
		);
		$fetchOptions = array(
			// 'readUserId' => XenForo_Visitor::getUserId(), -- disable this to save some headeach of db join
			// 'includeForumReadDate' => true, -- this's not necessary too
			'limit' => $widget['options']['limit'],
			'join' => XenForo_Model_Thread::FETCH_AVATAR,
			
		);
		
		if (in_array($widget['options']['type'], array('new', 'all'))) {
			$new = $threadModel->getThreads(
				$conditions
				, array_merge($fetchOptions, array(
					'order' => 'post_date',
					'orderDirection' => 'desc',
				))
			);
		} else {
			$new = array();
		}
		
		if (in_array($widget['options']['type'], array('recent', 'all'))) {
			$recent = $threadModel->getThreads(
				$conditions
				, array_merge($fetchOptions, array(
					'order' => 'last_post_date',
					'orderDirection' => 'desc',
					'join' => 0,
					'last_post_join' => XenForo_Model_Thread::FETCH_AVATAR,
				))
			);
			
			foreach ($recent as &$thread) {
				$thread['user_id'] = $thread['last_post_user_id'];
				$thread['username'] = $thread['last_post_username'];
			}
		} else {
			$recent = array();
		}
		
		if (in_array($widget['options']['type'], array('popular', 'all'))) {
			$popular = $threadModel->getThreads(
				array_merge($conditions, array(
					'post_date' => array('>', XenForo_Application::$time - $widget['options']['cutoff']*86400),
				))
				, array_merge($fetchOptions, array(
					'order' => 'view_count',
					'orderDirection' => 'desc',
				))
			);
		} else {
			$popular = array();
		}
		
		if (in_array($widget['options']['type'], array('polls', 'all'))) {
			$polls = $threadModel->getThreads(
				array_merge($conditions, array(
					'discussion_type' => 'poll',
				))
				, array_merge($fetchOptions, array(
					'order' => 'post_date',
					'orderDirection' => 'desc',
				))
			);
		} else {
			$polls = array();
		}
		
		$renderTemplateObject->setParam('new', $new);
		$renderTemplateObject->setParam('recent', $recent);
		$renderTemplateObject->setParam('popular', $popular);
		$renderTemplateObject->setParam('polls', $polls);
		
		return $renderTemplateObject->render();		
	}
}