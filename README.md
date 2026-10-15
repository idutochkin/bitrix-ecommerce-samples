## 01-cacharel-checkout

Интернет-магазин одежды. Своё оформление заказа (`cacharel:order`) вместо `sale.order.ajax`, на D7 Sale: курьер, ПВЗ Ozon, самовывоз с проверкой наличия, купоны, профили покупателя, пересчёт без перезагрузки.

## 02-britannica-restaurant-order

Ресторан. Модуль `westpower.fiji`: заказ и оплата со столика с синхронизацией с кассой R-Keeper, частичная оплата, очередь вызова официанта.

- `lib/Service/OrderService.php` - корзина, заказ, оплата, синхронизация с кассой
- `lib/Repository/Client/RKeeperClient.php` - XML-интерфейс R-Keeper
- `lib/Controller/OrderController.php` - AJAX и уведомления от кассы

## 03-winner-orders-payments

Онлайн-платформа (бэкенд и Vue). Модуль `westpower.winnnner`, главное - платёжная часть:

- `lib/PaySystem/Handlers/Base.php` - общий класс платёжки, реализации `Cryptomus`, `Pult24`, `Inner`
- `lib/PaySystem/Manager.php` + `lib/Controller/PaymentController.php` - приём уведомлений с проверкой подписи
