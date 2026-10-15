<?
namespace Westpower\Fiji\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Westpower\Fiji\Service\PagerService;

class PagerController extends Controller
{
    /**
     * @return \string[][][]
     */
    public function configureActions(): array
    {
        return [
            'checkin' => [
                '-prefilters' => [
                    ActionFilter\Authentication::class,
                    ActionFilter\Csrf::class,
                ],
            ],
        ];
    }

    /**
     * @param int $pagerId
     * @param int $waiterCode
     */
    public function checkinAction(int $pagerId, int $waiterCode)
    {
        PagerService::checkin($pagerId, $waiterCode);
    }
}