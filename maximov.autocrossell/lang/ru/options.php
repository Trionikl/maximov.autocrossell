<?php

/**
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */
$MESS['AUTOCROSSELL_OPTIONS_TITLE'] = 'Настройки AutoCrossell';
$MESS['AUTOCROSSELL_ACCESS_DENIED'] = 'Доступ запрещён';
$MESS['AUTOCROSSELL_SETTINGS_SAVED'] = 'Настройки успешно сохранены';

// Вкладки
$MESS['AUTOCROSSELL_TAB_MAIN'] = 'Основные настройки';
$MESS['AUTOCROSSELL_TAB_MAIN_TITLE'] = 'Параметры работы модуля';
$MESS['AUTOCROSSELL_TAB_AGENT'] = 'Агент';
$MESS['AUTOCROSSELL_TAB_AGENT_TITLE'] = 'Настройки фонового агента';

// Основные настройки
$MESS['AUTOCROSSELL_MIN_THRESHOLD'] = 'Минимальный порог';
$MESS['AUTOCROSSELL_MIN_THRESHOLD_TITLE'] = 'Минимальное количество совместных покупок для формирования пары';
$MESS['AUTOCROSSELL_HISTORY_DAYS'] = 'Глубина истории (дней)';
$MESS['AUTOCROSSELL_HISTORY_DAYS_TITLE'] = 'Период анализа истории заказов';
$MESS['AUTOCROSSELL_DEBUG'] = 'Включить отладку';
$MESS['AUTOCROSSELL_DEBUG_TITLE'] = 'Записывать отладочные логи в папку logs/ модуля';

// Настройки агента
$MESS['AUTOCROSSELL_AGENT_WINDOW_START'] = 'Время начала окна';
$MESS['AUTOCROSSELL_AGENT_WINDOW_START_TITLE'] = 'Начало временного окна выполнения агента';
$MESS['AUTOCROSSELL_AGENT_WINDOW_END'] = 'Время окончания окна';
$MESS['AUTOCROSSELL_AGENT_WINDOW_END_TITLE'] = 'Конец временного окна выполнения агента';
$MESS['AUTOCROSSELL_AGENT_INTERVAL'] = 'Интервал выполнения';
$MESS['AUTOCROSSELL_AGENT_INTERVAL_TITLE'] = 'Периодичность запуска агента';
$MESS['AUTOCROSSELL_AGENT_INTERVAL_HOURS'] = 'часов';
$MESS['AUTOCROSSELL_CALCULATING'] = '⏳ Пересчёт выполняется...';
$MESS['AUTOCROSSELL_RUN_NOW'] = '▶ Пересчитать сейчас';
$MESS['AUTOCROSSELL_RECALC_CONFIRM'] = 'Запустить пересчёт блока «С этим товаром покупают»? Это может занять несколько минут.';
$MESS['AUTOCROSSELL_RECALC_CONFIRM_TITLE'] = 'Подтверждение действия';
$MESS['AUTOCROSSELL_RECALC_CONFIRM_YES'] = 'Да';
$MESS['AUTOCROSSELL_RECALC_CONFIRM_NO'] = 'Нет';

$MESS['AUTOCROSSELL_RECALC_SUCCESS'] = 'Пересчёт блока «С этим товаром покупают» завершён. Сохранено пар: #COUNT#.';
$MESS['AUTOCROSSELL_RECALC_EMPTY'] = 'Пересчёт выполнен, но пары не сохранены (0 пар с весом >= минимального порога).';
$MESS['AUTOCROSSELL_RECALC_EMPTY_DETAIL'] = 'Причины: в окне истории нет заказов со статусом «оплачен»/«завершён», либо ни одна пара товаров не набрала пороговый вес. Проверьте настройки модуля и статусы заказов в базе. Успешный пересчёт без результата — не «успех»: см. журнал модуля.';
$MESS['AUTOCROSSELL_RECALC_ERROR'] = 'Произошла ошибка при пересчёте пар.';
$MESS['AUTOCROSSELL_RECALC_ERROR_DETAIL'] = 'Не удалось выполнить пересчёт. Подробности в журнале ошибок.';
$MESS['AUTOCROSSELL_RECALC_NO_DATA'] = 'Нет данных для расчёта.';
$MESS['AUTOCROSSELL_RECALC_LOCKED'] = 'Расчёт уже выполняется. Попробуйте позже.';
$MESS['AUTOCROSSELL_DEBUG_LOG_NOT_WRITABLE'] = 'Запись отладочных логов недоступна: папка модуля закрыта на запись на уровне сервера. Пересчёт работает, но файловые логи не создаются.';
$MESS['AUTOCROSSELL_BLOCK_TITLE'] = 'Заголовок блока';
$MESS['AUTOCROSSELL_BLOCK_TITLE_TITLE'] = 'Текст заголовка блока рекомендаций на странице товара. Пустая строка — «С этим покупают».';
$MESS['AUTOCROSSELL_BLOCK_CONTAINER_SELECTOR'] = 'CSS-селектор контейнера для вставки';
$MESS['AUTOCROSSELL_BLOCK_CONTAINER_SELECTOR_TITLE'] = 'CSS-селектор DOM-элемента, куда виджет вставит блок (например: .workarea > footer). Пусто — перед &lt;footer&gt;.';