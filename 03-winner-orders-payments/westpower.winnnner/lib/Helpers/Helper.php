<?

namespace Westpower\Winnnner\Helpers;

use \Bitrix\Main\Grid\Options;

class Helper
{
    /**
     * @param int|string $int
     * @return string
     */
    public static function toIntFormat(string|int $int): string
    {
        return number_format($int, 0, '', ' ');
    }

    public static function sortGridByID($id, &$list)
    {
        $gridOptions = new Options($id);
        $sorting = $gridOptions->getSorting();
        $by = array_key_first($sorting['sort']);
        $order = $sorting['sort'][$by];
        uasort($list,function($a,$b) use ($by,$order){
            $result = 0;
            switch($order){
                case "asc":
                    $result = $a['for_sort'][$by]>=$b['for_sort'][$by];
                    break;
                case "desc":
                    $result = $a['for_sort'][$by]<=$b['for_sort'][$by];
                    break;
            }
            return $result;
        });
    }
}