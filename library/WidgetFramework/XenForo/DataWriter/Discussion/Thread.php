<?php

class WidgetFramework_XenForo_DataWriter_Discussion_Thread_Base extends XFCP_WidgetFramework_XenForo_DataWriter_Discussion_Thread
{
	protected function _postSaveAfterTransaction()
	{
		// commented out due to problem with high traffic board
		// since 1.3
		// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		// WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');

		return parent::_postSaveAfterTransaction();
	}

	protected function _WidgetFramework_clearCachedWidgets()
	{
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Threads');
		WidgetFramework_Core::clearCachedWidgetByClass('WidgetFramework_WidgetRenderer_Poll');
	}

}

if (XenForo_Application::$versionId < 1020000)
{
	// old versions
	class WidgetFramework_XenForo_DataWriter_Discussion_Thread extends WidgetFramework_XenForo_DataWriter_Discussion_Thread_Base
	{
		protected function _discussionPostDelete(array $messages)
		{
			$this->_WidgetFramework_clearCachedWidgets();

			return parent::_discussionPostDelete($messages);
		}

	}

}
else
{
	// v1.2+
	class WidgetFramework_XenForo_DataWriter_Discussion_Thread extends WidgetFramework_XenForo_DataWriter_Discussion_Thread_Base
	{
		protected function _discussionPostDelete()
		{
			$this->_WidgetFramework_clearCachedWidgets();

			return parent::_discussionPostDelete();
		}

	}

}
