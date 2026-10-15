<?
namespace Westpower\Winnnner\Agent;

use Exception;
use CEventLog;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/*
 * Класс по минификации JS-файлов. Использует NodeJS плагин uglifyjs
 */

class JsMinify
{

    const DIRS_FOR_JS_MINIFY = [
        '/local/',
    ];

    /**
     * Агент по минификации скриптов
     *
     * @return string
     */
    public static function minifyAgent()
    {
        try {
            self::minify();
        } catch (Exception $obException) {
            CEventLog::Add(array(
                "SEVERITY"      => "ERROR",
                "AUDIT_TYPE_ID" => "MINIFY_JS",
                "MODULE_ID"     => "main",
                "ITEM_ID"       => "",
                "DESCRIPTION"   => $obException->getMessage()
            ));
        }
        return __METHOD__.'();';
    }

    /**
     * Минификация скриптов, находящихся внутри директорий, обозначенных в константе \dh\JsMinify::DIRS_FOR_JS_MINIFY
     */
    public static function minify()
    {
        $bNeedCacheRefresh = false;
        foreach (self::DIRS_FOR_JS_MINIFY as $sDir) {
            $arFilePaths = self::findJsFilesInDir($sDir);
            if (!empty($arFilePaths)) {
                foreach ($arFilePaths as $sFilePath) {
                    $sMinFilePath = str_replace('.js', '.min.js', $sFilePath);
                    if (
                        !file_exists($sMinFilePath) ||
                        (
                            file_exists($sMinFilePath) &&
                            filectime($sFilePath) > filectime($sMinFilePath)
                        )
                    ) {
                        /*
                         * https://www.npmjs.com/package/uglify-js
                         -c, --compress [options]    Enable compressor/specify compressor options
                         -m, --mangle [options]      Mangle names/specify mangler options
                         -o, --output <file>         Output file path (default STDOUT)
                         */
                        shell_exec(self::getUglifyJsPath().' '.$sFilePath.' -c -m -o '.$sMinFilePath);
                        $bNeedCacheRefresh = true;
                    }
                }
            }
        }

        if ($bNeedCacheRefresh && BXClearCache(true, "/js/")) {
            CEventLog::Add(array(
                "SEVERITY"      => "INFO",
                "AUDIT_TYPE_ID" => "MINIFY_JS",
                "MODULE_ID"     => "main",
                "ITEM_ID"       => "",
                "DESCRIPTION"   => "Clear cache"
            ));
        }
    }

    /**
     * Получим путь до UglifyJS
     * @return string
     */
    private static function getUglifyJsPath() {
        return '/usr/local/bin/uglifyjs';
    }

    /**
     * Поиск Не минифицированных js файлов в Директории (и её поддиректориях)
     *
     * @param $sDirPath
     * @return array
     */
    public static function findJsFilesInDir($sDirPath)
    {
        $arFilePaths = [];
        $obDirectory = new RecursiveDirectoryIterator($_SERVER["DOCUMENT_ROOT"].$sDirPath);
        $obIterator = new RecursiveIteratorIterator($obDirectory);

        foreach ($obIterator as $obInfo) {
            $file_formal = substr($obInfo->getfileName(), strrpos($obInfo->getfileName(), ".") + 1);
            $name_search = array("js"); // Список форматов
            foreach ($name_search as $key_name) {
                if (
                    $file_formal == $key_name &&
                    !stristr($obInfo->getfileName(), '.min.js') &&
                    !stristr($obInfo->getfileName(), '.map.js')
                ) {
                    $arFilePaths[] = $obInfo->getPathname();
                }
            }
        }

        return $arFilePaths;
    }

}