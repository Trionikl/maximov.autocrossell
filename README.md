# Автоматический кросс-сейл (`maximov.autocrossell`)

Модуль **«Автоматический кросс-сейл»** для «1С-Битрикс: Управление сайтом»:
на основе истории реальных заказов автоматически формирует блок
рекомендаций «С этим товаром покупают» и выводит его на страницах товаров.
Ручная разметка связей между товарами не требуется.

- **Решение в Маркетплейсе:** https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
- **[Установка](https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/#tab-install-link)**
- **[Поддержка](https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/#tab-support-link)**

## Как это работает

- **D7 ORM.** Пары товаров хранятся в таблице `b_maximov_autocrossell_pairs`
  (сущность `Bitrix\Main\Entity\DataManager`), настройки — через
  `Bitrix\Main\Config\Option`.
- **Фоновый агент с замком БД.** `RecalculationAgent` пересчитывает пары по
  расписанию (`AgentScheduler`). Защита от параллельного запуска — нативный
  D7-замок СУБД (`AgentGuard` через `Application::getConnection()->lock()`).
- **OnProlog для ассетов.** Событие `main/OnProlog` подключает JS/CSS-виджет:
  клиентский скрипт монтирует скелетон в заданную точку страницы, затем
  подменяет его HTML из D7 AJAX-контроллера
  `maximov:autocrossell.detail.getComponentHtml`.
- **Компонент.** Серверный рендер карточек — компонент
  `maximov.autocrossell:recommendations` (шаблон `.default`), кеширование
  ядра с тегом `maximov.autocrossell`.

## Установка

1. Скопируйте папку `maximov.autocrossell` из этого репозитория в директорию
   `/bitrix/modules/` вашего сайта.
2. Откройте админку: **Настройки → Настройки продукта → Настройки модулей**
   и нажмите **«Установить»** у `maximov.autocrossell`.
3. Настройте параметры модуля на странице настроек.

Подробная инструкция — в разделе «Установка» на странице решения
в Маркетплейсе.

## Тестирование

Чек-лист ручного тестирования — в [TESTING.md](TESTING.md).
HTML-чеклисты разработчика лежат в папке [`tests/`](tests/)
(скопируйте её в корень сайта и откройте `tests/index.php` в браузере).

## Лицензия

Модуль распространяется под лицензией
[GNU General Public License v3](LICENSE) (GPLv3).

Copyright (C) 2026 Denis Maximov