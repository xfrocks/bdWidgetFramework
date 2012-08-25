<?php
class WidgetFramework_WidgetRenderer_Threads extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Threads',
			'options' => array(
				'type' => XenForo_Input::STRING,
				'cutoff' => XenForo_Input::UINT,
				'forums' => XenForo_Input::ARRAY_SIMPLE,
				'as_guest' => XenForo_Input::UINT,
				'limit' => XenForo_Input::UINT,
			),
			'useCache' => true,
			'useUserCache' => true,
			'cacheSeconds' => 300, // cache for 5 minutes
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_threads';
	}
	
	protected function _renderOptions(XenForo_Template_Abstract $template) {
		$params = $template->getParams();

		$forums = $this->_helperPrepareForumsOptionSource(
			empty($params['options']['forums']) ? array(): $params['options']['forums'],
			true
		);
		
		$template->setParam('forums', $forums);
	}
	
	protected function _validateOptionValue($optionKey, &$optionValue) {
		if ('type' == $optionKey) {
			if (!in_array($optionValue, array('new', 'recent', 'popular', 'most_replied', 'most_liked', 'polls'))) {
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
		$visitor = XenForo_Visitor::getInstance();

		$forumIds = $this->_helperGetForumIdsFromOption(
			$widget['options']['forums'],
			$params,
			empty($widget['options']['as_guest']) ? false : true
		);
		
		$conditions = array(
			'node_id' => $forumIds,
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
		
		if (in_array($widget['options']['type'], array('most_replied', 'all'))) {
			$mostReplied = $threadModel->getThreads(
				array_merge($conditions, array(
					'post_date' => array('>', XenForo_Application::$time - $widget['options']['cutoff']*86400),
				))
				, array_merge($fetchOptions, array(
					'order' => 'reply_count',
					'orderDirection' => 'desc',
				))
			);
			
			foreach (array_keys($mostReplied) as $postId) {
				if ($mostReplied[$postId]['reply_count'] == 0) {
					// remove threads with zero reply_count
					unset($mostReplied[$postId]);
				}
			}
		} else {
			$mostReplied = array();
		}
		
		if (in_array($widget['options']['type'], array('most_liked', 'all'))) {
			$mostLiked = $threadModel->getThreads(
				array_merge($conditions, array(
					'post_date' => array('>', XenForo_Application::$time - $widget['options']['cutoff']*86400),
				))
				, array_merge($fetchOptions, array(
					'order' => 'first_post_likes',
					'orderDirection' => 'desc',
				))
			);
			
			foreach (array_keys($mostLiked) as $postId) {
				if ($mostLiked[$postId]['first_post_likes'] == 0) {
					// remove threads with zero first_post_likes
					unset($mostLiked[$postId]);
				}
			}
		} else {
			$mostLiked = array();
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
		$renderTemplateObject->setParam('mostReplied', $mostReplied);
		$renderTemplateObject->setParam('mostLiked', $mostLiked);
		$renderTemplateObject->setParam('polls', $polls);
		
		return $renderTemplateObject->render();		
	}
	
	public function useUserCache(array $widget) {
		if (!empty($widget['options']['as_guest'])) {
			// using guest permission
			// there is no reason to use the user cache
			return false;
		}
		
		return parent::useUserCache($widget);
	}
	
	protected function _getCacheId(array $widget, $positionCode, array $params, array $suffix = array()) {
		if ($this->_helperDetectSpecialForums($widget['options']['forums'])) {
			// we have to use special cache id when special forum ids are used
			if (isset($params['forum'])) {
				$suffix[] = 'f' . $params['forum']['node_id'];
			}
		}
		
		return parent::_getCacheId($widget, $positionCode, $params, $suffix);
	}
}