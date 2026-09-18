# ЮKassa: что проверить после DNS

Живой платёж картой на `localtest.me` не делаем: webhook ЮKassa не достучится до локалки. Ниже — кабинет кассы и прод, не списание с тестовой карты у себя.

Плагин в репозитории: `wordpress/wp-content/plugins/yookassa` (ЮKassa для WooCommerce). Уведомления он вешает сам на HTTPS:

`https://www.acoedu.ru/?yookassa=callback`

Не используйте устаревший `/?wc-api=wc_yookassa` — текущий плагин его не слушает.

## В кабинете ЮKassa

1. Сначала **тестовый магазин**. Боевой shopId — только после успешного тестового платежа на проде.
2. ShopId и секретный ключ совпадают с опциями `yookassa_shop_id` / `yookassa_shop_password` в WordPress.
3. HTTP-уведомления: URL `https://<сайт>/?yookassa=callback`, события payment.succeeded / canceled / waiting_for_capture и refund.succeeded. Плагин создаёт их сам после сохранения настроек, если сайт уже на HTTPS.
4. В списке уведомлений нет старых URL (`localtest.me`, `wc-api`).
5. Чеки 54-ФЗ включены в кабинете, если шлёте чеки из плагина.

## На сайте (после HTTPS)

- `WP_HOME` / `WP_SITEURL` с `https://`.
- `ACO_ENABLE_COD_PLACEHOLDER=0`. Если shopId задан, тема не поднимает COD «Онлайн-оплата» (`functions.php`). В админке WooCommerce COD должен быть выключен.
- Шлюз ЮKassa включён. Карта на нашем чекауте не вводится: редирект на форму ЮKassa, возврат на thank-you. Повтор оплаты — кнопка на `woocommerce/checkout/thankyou.php`.
- Email на чекауте обязателен (чеки). Сумма заказа = сумма платежа. Товары виртуальные, без доставки.
- Чек 54-ФЗ: `yookassa_enable_receipt=1`. НДС и СНО в плагине должны совпадать с карточкой магазина. Сейчас в локальной базе `yookassa_default_tax_rate=6` — этого кода нет в актуальном справочнике плагина (0% / 5% / 7% / 10% / 22% и расчётные). Перед боем выставить ставку и `yookassa_default_tax_system_code`. Признак предмета — `service`, признак расчёта — `full_payment` (цифровой курс).
- Второй чек (`yookassa_enable_second_receipt`) для предоплаты не нужен, если сразу `full_payment`.
- На кнопке оплаты: оферта, ПДн и политика конфиденциальности.

## Локально это не блокер хостинга

- shopId пустой — ожидаемо, пока нет кассы. Тогда работает заглушка COD «Онлайн-оплата» (`ACO_ENABLE_COD_PLACEHOLDER=1`).
- Webhook на `wp.localtest.me` ЮKassa не доставит.
