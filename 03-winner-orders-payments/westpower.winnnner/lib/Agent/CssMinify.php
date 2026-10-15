<?
namespace Westpower\Winnnner\Agent;

use Exception;
use CEventLog;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/*
 * Класс по минификации CSS-файлов. Использует NodeJS плагин uglifycss
 */

class CssMinify
{

    const DIRS_FOR_CSS_MINIFY = [
        '/local/',
    ];

    const FILES_FOR_CSS_MINIFY = [];

    private static $bNeedCacheRefresh = false;

    /**
     * Агент по минификации стилей
     *
     * @return string
     */
    public static function minifyAgent(): string
    {
        try {
            self::minify();
        } catch (Exception $obException) {
            CEventLog::Add(array(
                "SEVERITY"      => "ERROR",
                "AUDIT_TYPE_ID" => "MINIFY_CSS",
                "MODULE_ID"     => "main",
                "ITEM_ID"       => "",
                "DESCRIPTION"   => $obException->getMessage()
            ));
        }
        return __METHOD__.'();';
    }

    /**
     * Минификация стилей, находящихся внутри директорий, обозначенных в константе \dh\CssMinify::DIRS_FOR_CSS_MINIFY,
     * и в файлах в константе \dh\Seo\CssMinify::FILES_FOR_CSS_MINIFY
     */
    public static function minify()
    {
        foreach (self::DIRS_FOR_CSS_MINIFY as $sDir) {
            $arFilePaths = self::findCssFilesInDir($sDir);
            if (!empty($arFilePaths)) {
                foreach ($arFilePaths as $sFilePath) {
                    self::execUglifycss($sFilePath);
                }
            }
        }

        foreach (self::FILES_FOR_CSS_MINIFY as $sFilePath) {
            self::execUglifycss($sFilePath, true);
        }

        if (self::$bNeedCacheRefresh && BXClearCache(true, "/css/")) {
            CEventLog::Add(array(
                "SEVERITY"      => "INFO",
                "AUDIT_TYPE_ID" => "MINIFY_CSS",
                "MODULE_ID"     => "main",
                "ITEM_ID"       => "",
                "DESCRIPTION"   => "Clear cache"
            ));
        }
    }

    /**
     * Получим путь до UglifyCss
     * @return string
     */
    private static function getUglifyCssPath(): string
    {
        return '/usr/local/bin/uglifycss';
    }

    /**
     * Поиск Не минифицированных css файлов в Директории (и её поддиректориях)
     *
     * @param $sDirPath
     * @return array
     */
    public static function findCssFilesInDir($sDirPath): array
    {
        $arFilePaths = [];
        $obDirectory = new RecursiveDirectoryIterator($_SERVER["DOCUMENT_ROOT"].$sDirPath);
        $obIterator = new RecursiveIteratorIterator($obDirectory);

        foreach ($obIterator as $obInfo) {
            $file_formal = substr($obInfo->getfileName(), strrpos($obInfo->getfileName(), ".") + 1);
            $name_search = array("css"); // Список форматов
            foreach ($name_search as $key_name) {
                if (
                    $file_formal == $key_name &&
                    !stristr($obInfo->getfileName(), '.min.css')
                ) {
                    $arFilePaths[] = $obInfo->getPathname();
                }
            }
        }

        return $arFilePaths;
    }

    /**
     * Выполнить минификацию
     *
     * @param $sFilePath
     * @param bool $bAddDocumentRoot
     */
    private static function execUglifycss($sFilePath, bool $bAddDocumentRoot = false)
    {
        if ($bAddDocumentRoot) {
            $sFilePath = $_SERVER["DOCUMENT_ROOT"].$sFilePath;
        }

        $sMinFilePath = str_replace('.css', '.min.css', $sFilePath);
        if (
            !file_exists($sMinFilePath) ||
            (
                file_exists($sMinFilePath) &&
                filectime($sFilePath) > filectime($sMinFilePath)
            )
        ) {
            /*
             * https://www.npmjs.com/package/uglifycss
             --output f puts the result in f file
             */
            shell_exec(self::getUglifyCssPath().' '.$sFilePath.' --output '.$sMinFilePath);
            self::$bNeedCacheRefresh = true;
        }
    }

}