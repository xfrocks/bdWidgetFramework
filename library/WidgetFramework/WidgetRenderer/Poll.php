<?php
class WidgetFramework_WidgetRenderer_Poll extends WidgetFramework_WidgetRenderer {
	protected function _getConfiguration() {
		return array(
			'name' => 'Poll',
			'options' => array(
				'thread_id' => XenForo_Input::UINT,
			),
			'useCache' => true,
			'useUserCache' => true,
		);
	}
	
	protected function _getOptionsTemplate() {
		return 'wf_widget_options_poll';
	}
	
	protected function _validateOptionValue($optionKey, &$optionValue) {
		if ('thread_id' == $optionKey AND !empty($optionValue)) {
			$threadModel = XenForo_Model::create('XenForo_Model_Thread');
			$thread = $threadModel->getThreadById($optionValue);
			if (empty($thread)) {
				throw new XenForo_Exception(new XenForo_Phrase('requested_thread_not_found'), true);
			} else if (empty($thread['discussion_type']) OR 'poll' != $thread['discussion_type']) {
				throw new XenForo_Exception(new XenForo_Phrase('wf_requested_thread_does_not_have_poll'), true);
			}
		}
		
		return true;
	}
	
	protected function _getRenderTemplate(array $widget, $templateName, array $params) {
		return 'wf_widget_poll';
	}
	
	protected function _getRequiredExternal(array $widget) {
		return array(
			array('css', 'wf_default'), 
			array('css', 'polls'),
			array('js', 'js/xenforo/discussion.js'),
		);
	}
	
	protected function _render(array $widget, $templateName, array $params, XenForo_Template_Abstract $renderTemplateObject) {
		$core = WidgetFramework_Core::getInstance();
		$threadModel = $core->getModelFromCache('XenForo_Model_Thread');
		$pollModel = $core->getModelFromCache('XenForo_Model_Poll');

		$thread = array();
		$poll = array();
		
		if (empty($widget['options']['thread_id'])) {
			if (empty($GLOBALS['WidgetFramework_viewableNodeList'])) {
				$nodeModel = $core->getModelFromCache('XenForo_Model_Node');
				$GLOBALS['WidgetFramework_viewableNodeList'] = $nodeModel->getViewableNodeList();
			}
			$forumIds = array_keys($GLOBALS['WidgetFramework_viewableNodeList']);
			
			$thread = $threadModel->getThreads(
				array(
					'forum_ids' => $forumIds,
					'discussion_type' => 'poll',
				)
				,array(
					'order' => 'post_date',
					'orderDirection' => 'desc',
					'poll_join' => true,
					'forum_full_join' => true,
					'limit' => 1,
				)
			);

			if (!empty($thread)) {
				$thread = array_shift($thread);
				$poll = $pollModel->preparePoll($thread, $threadModel->canVoteOnPoll($thread, $thread));
			}
		} else {
			$thread = $threadModel->getThreadById(
				$widget['options']['thread_id']
				,array(
					'poll_join' => true,
					'forum_full_join' => true,
				)
			);

			if ($thread['discussion_type'] != 'poll') {
				$thread = array();
			}
		}
		if (!empty($thread)) $poll = $pollModel->preparePoll($thread, $threadModel->canVoteOnPoll($thread, $thread));
		
		$renderTemplateObject->setParam('thread', $thread);
		$renderTemplateObject->setParam('poll', $poll);

		return $renderTemplateObject->render();		
	}
}