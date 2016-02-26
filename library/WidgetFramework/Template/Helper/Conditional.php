<?php

class WidgetFramework_Template_Helper_Conditional
{
    /**
     * Checks if the current request is a POST request.
     *
     * Usage: {xen:helper wf_isHttpPost}
     *
     * @return bool
     */
    public function isHttpPost()
    {
        if (!empty($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST'
        ) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the $post is the one specified in the $unreadLink. If the $unreadLink is
     * empty or there is no post id in the link, true will be return asap.
     * Please note that for the entire request, this method only return true once.
     *
     * Usage: {xen:helper wf_unreadLinkPost, $unreadLink, $post, $posts}
     * Recommended position: hook:message_below
     *
     * @param string $unreadLink
     * @param array $post
     * @param array $posts
     * @return bool
     */
    public function unreadLinkPost($unreadLink, $post, $posts)
    {
        static $found = false;
        static $postFragment = '#post-';

        if ($found) {
            // return true once
            return false;
        }

        if (!is_array($post)
            || !isset($post['post_id'])
            || !is_array($posts)
        ) {
            // incorrect usage...
            if (XenForo_Application::debugMode()) {
                XenForo_Error::logError('{xen:helper wf_unreadLinkPost} requires (string $unreadLink),'
                    . ' (array $post), (array $posts)');
            }

            $found = true;
        } else {
            $postPos = strpos($unreadLink, $postFragment);
            if ($postPos === false) {
                // implementation 1: wait for the last post and return true
//                $postIds = array_keys($posts);
//                $lastPostId = array_pop($postIds);
//                $found = $lastPostId == $post['post_id'];
                // implementation 2: return true asap
                $found = true;
            } else {
                // return true for the specified unread post
                $unreadLinkPostId = substr($unreadLink, $postPos + strlen($postFragment));
                $found = $unreadLinkPostId == $post['post_id'];
            }
        }

        return $found;
    }

    protected $_helperCallbacks = array();

    private function __construct()
    {
    }

    protected function _setupHelpers()
    {
        $conditionalHelper = new ReflectionClass(get_class($this));
        foreach ($conditionalHelper->getMethods() as $method) {
            $methodModifiers = $method->getModifiers();
            if ($methodModifiers & ReflectionMethod::IS_PUBLIC
                && !($methodModifiers & ReflectionMethod::IS_STATIC)
            ) {
                $methodName = $method->getName();
                $helperCallbackName = strtolower('wf_' . $methodName);

                $this->_helperCallbacks[$helperCallbackName] = array($this, $methodName);
            }
        }
    }

    /**
     * @param string $helperCallbackName
     * @return bool
     */
    protected function _hasHelper($helperCallbackName)
    {
        return isset($this->_helperCallbacks[$helperCallbackName]);
    }

    /**
     * @param string $helperCallbackName
     * @param array $args
     * @return mixed
     */
    protected function _callHelper($helperCallbackName, array $args)
    {
        return call_user_func_array($this->_helperCallbacks[$helperCallbackName], $args);
    }

    /**
     * Calls our helper or uses the generic helper.
     *
     * @param string $helper Name of helper
     * @param array $args All arguments passed to the helper.
     *
     * @return string
     */
    public static function callHelper($helper, array $args)
    {
        /** @var static $instance */
        static $instance = null;

        if ($instance === null) {
            $class = XenForo_Application::resolveDynamicClass(__CLASS__, __CLASS__);
            $instance = new $class;
            $instance->_setupHelpers();
        }

        $helper = strtolower(strval($helper));
        if ($instance->_hasHelper($helper)) {
            return $instance->_callHelper($helper, $args);
        }

        return XenForo_Template_Helper_Core::callHelper($helper, $args);
    }
}