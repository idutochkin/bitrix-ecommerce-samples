<?
namespace Westpower\Fiji\Agent;

use Bitrix\Sale\Internals;
use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Westpower\Fiji\Repository\OptionRepository;
use Westpower\Fiji\Service\PagerService;

Loader::includeModule('sale');

class OrderAgent
{
    const BASKET_DELETE_LIMIT = 2000;

    /**
     * @param bool $checkTime
     * @return string
     */
    public static function cashDayClosing(bool $checkTime = true): string
    {
        if (!$checkTime || (int) (new DateTime)->format('H') === (int) OptionRepository::get('cash_day_closing_time', 10)) {
            self::basketDeleteOld();
            self::fUserDeleteOld();
            PagerService::resetCheckin();
        }

        return __METHOD__ . '();';
    }

    /**
     * @return int
     */
    private static function basketDeleteOld(): int
    {
        $connection = Main\Application::getConnection();
        $helper = $connection->getSqlHelper();

        $query = $helper->prepareDeleteLimit(
            Internals\BasketTable::getTableName(),
            ['ID'],
            "
				FUSER_ID IN (
				SELECT b_sale_fuser.ID FROM b_sale_fuser WHERE
						b_sale_fuser.USER_ID IS NULL
				) OR ORDER_ID IS NULL
			",
            [],
            static::BASKET_DELETE_LIMIT
        );

        $connection->queryExecute($query);
        $affectRows = $connection->getAffectedRowsCount();

        $query = $helper->prepareDeleteLimit(
            Internals\BasketTable::getTableName(),
            ['ID'],
            "
				FUSER_ID NOT IN (SELECT b_sale_fuser.ID FROM b_sale_fuser)
				AND ORDER_ID IS NULL
			",
            [],
            static::BASKET_DELETE_LIMIT
        );

        $connection->queryExecute($query);
        $affectRows = max($affectRows, $connection->getAffectedRowsCount());

        $query = $helper->prepareDeleteLimit(
            Internals\BasketPropertyTable::getTableName(),
            ['ID'],
            "
				BASKET_ID NOT IN (
					SELECT b_sale_basket.ID FROM b_sale_basket
				)
			",
            [],
            static::BASKET_DELETE_LIMIT
        );

        $connection->queryExecute($query);

        return max($affectRows, $connection->getAffectedRowsCount());
    }

    /**
     *
     */
    private static function fUserDeleteOld(): void
    {
        $connection = Main\Application::getConnection();

        $query = "
			DELETE FROM b_sale_fuser
			WHERE
				b_sale_fuser.USER_ID IS NULL
				AND b_sale_fuser.ID NOT IN (select FUSER_ID from b_sale_basket)
		";

        $connection->queryExecute($query);
    }
}