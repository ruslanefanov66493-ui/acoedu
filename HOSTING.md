# Выкладка АЦО на VPS

Боевой домен сейчас **acoedu.ru**, хостинг — Timeweb Cloud MSK 100. Пошаговый план на день: [docs/timeweb-acoedu.md](../docs/timeweb-acoedu.md).

Стек тот же, что локально: Nginx + WordPress (PHP 8.2 FPM) + Moodle (PHP 8.1 Apache за прокси) + MariaDB + Redis.

## 1. Сервер

Ubuntu 22.04/24.04, 4 GB RAM минимум, Docker Engine + Compose v2. DNS:

- `www.acoedu.ru` и `acoedu.ru` → A-запись VPS (сайт)
- `learn.acoedu.ru` → A-запись VPS (Moodle)

Конфиги nginx уже заточены под эти имена. Первичный выпуск сертификата — через `nginx/bootstrap`, см. timeweb-док.

## 2. Код и секреты

```bash
git clone <repo> /opt/aco && cd /opt/aco
cp .env.example .env
cp moodle/config-dist.php moodle/config.php
# Сгенерировать соли WP, пароли БД/Redis, SMTP
```

В `.env` обязательно:

- `WP_ENVIRONMENT_TYPE=production` — локальный mu-plugin allowlist не включится
- `ACO_ENABLE_COD_PLACEHOLDER=0` — не поднимать заглушку «Онлайн-оплата»
- `MOODLE_SSLPROXY=1`
- `MOODLE_WWWROOT=https://learn.acoedu.ru`
- `WP_HOME` / `WP_SITEURL` с https

## 3. Сертификаты

Первый выпуск (HTTP-only): временно оставьте в nginx только `listen 80` и `location /.well-known/acme-challenge/`, затем:

```bash
docker compose -f docker-compose.prod.yml up -d nginx
docker run --rm -v /opt/aco/certs/letsencrypt:/etc/letsencrypt \
  -v /opt/aco/certs/certbot:/var/www/certbot \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  -d acoedu.ru -d www.acoedu.ru -d learn.acoedu.ru --email inbox@acoedu.ru --agree-tos
```

Верните SSL-серверы и `docker compose -f docker-compose.prod.yml up -d`.

## 4. Запуск

```bash
docker compose -f docker-compose.prod.yml up -d --build
docker exec aco-moodle php /var/www/html/admin/cli/purge_caches.php
```

Cron Moodle и WP уже в compose (`moodle-cron`, `wp-cron`).

Бэкап раз в сутки из crontab хоста:

```
0 3 * * * /opt/aco/deploy/backup.sh
```

## 5. После переноса базы с локалки

1. В Moodle: Site administration → Server → HTTP — вернуть `curlsecurityblockedhosts` (или `MOODLE_BLOCK_PRIVATE_HOSTS=1` в `config-dist.php`).
2. Edwiser: URL Moodle/WP на боевые https, новый SSO shared secret в Moodle `auth_edwiserbridge/sharedsecret` и WP option `aco_eb_sso_secret`.
3. ЮKassa: shopId + секрет, webhook `https://www.acoedu.ru/?yookassa=callback` (плагин сам подписывается на HTTPS). Выключить COD «Онлайн-оплата». Чеклист: [docs/yookassa-precheck.md](../docs/yookassa-precheck.md).
4. Сменить пароли admin / Admin123!.
5. В Kinescope Allowed Domains — домен Moodle.
6. `WP_DEBUG=0`, не публиковать порты 3306/6379.
7. Почта: Unisender Go, DNS и ящики отдела — [docs/pochta-i-zayavki.md](../docs/pochta-i-zayavki.md).

## 6. Что не переносить с локалки

- Mailpit
- `edwiser-local-http-allowlist.php` (сам отключается при `WP_ENVIRONMENT_TYPE=production`)
- Демо-скрипты `moodle/eb_seed_content.php`, `eb_demo_attempt.php`
