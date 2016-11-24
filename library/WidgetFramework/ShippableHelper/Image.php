<?php

// updated by DevHelper_Helper_ShippableHelper at 2016-11-24T09:07:29+00:00

/**
 * Class WidgetFramework_ShippableHelper_Image
 * @version 10
 * @see DevHelper_Helper_ShippableHelper_Image
 */
class WidgetFramework_ShippableHelper_Image
{
    public static function getThumbnailUrl($imageUrl, $width, $height = 0, $dir = null)
    {
        if (defined('BDIMAGE_IS_WORKING')) {
            $size = $width;
            $mode = bdImage_Integration::MODE_CROP_EQUAL;
            if ($width > 0 && $height > 0) {
                $size = $width;
                $mode = $height;
            } elseif ($width > 0) {
                $size = $width;
                $mode = bdImage_Integration::MODE_STRETCH_HEIGHT;
            } elseif ($height > 0) {
                $size = $height;
                $mode = bdImage_Integration::MODE_STRETCH_WIDTH;
            }

            return bdImage_Integration::buildThumbnailLink($imageUrl, $size, $mode);
        }

        $thumbnailPath = self::getThumbnailPath($imageUrl, $width, $height, $dir);
        $thumbnailUrl = XenForo_Link::convertUriToAbsoluteUri(XenForo_Application::$externalDataUrl
            . self::_getThumbnailRelativePath($imageUrl, $width, $height, $dir), true);
        if (file_exists($thumbnailPath)) {
            $thumbnailFileSize = filesize($thumbnailPath);
            if ($thumbnailFileSize > 0) {
                return sprintf('%s?fs=%d', $thumbnailUrl, $thumbnailFileSize);
            }
        }

        $coreData = WidgetFramework_ShippableHelper_ImageCore::open($imageUrl);
        $coreData = WidgetFramework_ShippableHelper_ImageCore::thumbnail($coreData, $width, $height);
        WidgetFramework_ShippableHelper_ImageCore::save($coreData, $thumbnailPath);

        return sprintf('%s?t=%d', $thumbnailUrl, XenForo_Application::$time);
    }

    public static function getThumbnailPath($imageUrl, $width, $height = 0, $dir = null)
    {
        $thumbnailPath = XenForo_Helper_File::getExternalDataPath()
            . self::_getThumbnailRelativePath($imageUrl, $width, $height, $dir);

        return $thumbnailPath;
    }

    protected static function _getThumbnailRelativePath($imageUrl, $width, $height, $dir)
    {
        $path = parse_url($imageUrl, PHP_URL_PATH);
        $basename = basename($path);
        if (empty($basename)) {
            $basename = md5($imageUrl);
        }
        $fileName = preg_replace('#[^a-zA-Z0-9]#', '', $basename) . md5(serialize(array(
                'fullPath' => $imageUrl,
                'width' => $width,
                'height' => $height,
            )));
        $ext = XenForo_Helper_File::getFileExtension($basename);
        if (!in_array($ext, array('gif', 'jpeg', 'jpg', 'png'), true)) {
            $ext = 'jpg';
        }
        $divider = substr(md5($fileName), 0, 2);

        if (empty($dir)) {
            $dir = trim(str_replace('_', '/', substr(__CLASS__, 0, strpos(__CLASS__, '_ShippableHelper_Image'))), '/');
        }

        return sprintf('/%s/%sx%s/%s/%s.%s', $dir, $width, $height, $divider, $fileName, $ext);
    }
}