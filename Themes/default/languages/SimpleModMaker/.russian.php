<?php

/**
 * .russian.php (language file)
 *
 * @package Simple Mod Maker
 * @author Bugo https://dragomano.ru/mods/simple-mod-maker
 */

$txt['smm_desc'] = 'Добро пожаловать на страницу модификации для создания других модификаций 😜 <strong>Предупреждение</strong>: данный мод не сделает всю работу за вас, он лишь предоставит вам готовый скелет приложения, который вы сможете адаптировать под свои нужды. Воспринимайте код мода как учебное пособие и творите, творите больше интересных модов для SMF!';
$txt['smm_basic'] = 'Базовые настройки';
$txt['smm_generator'] = 'Генератор';

$txt['smm_mod_author'] = 'Разработчик';
$txt['smm_readme'] = 'Шаблон файлов readme';
$txt['smm_readme_desc'] = 'Доступны следующие переменные:';
$txt['smm_readme_vars'] = array(
	'mod_name' => 'Название мода',
	'author' => 'Имя автора',
	'description' => 'Описание',
	'license' => 'Лицензия'
);
$txt['smm_readme_default'] = '[center][color=red][size=16pt][b]{mod_name}[/b][/size][/color]
[color=blue][b][size=10pt]By {author}[/size][/b][/color]
[color=green]{description}[/color][/center]

[b]Фичи:[/b]
[list]
[li]Фича 1[/li]
[li]Фича 2[/li]
[li]Фича 3[/li]
[/list]

[hr][b]Распространяется по лицензии {license}.[/b]';

$txt['smm_add_desc'] = 'Генератор поможет подготовить дистрибутив мода. Внимательно заполните предлагаемые поля.';

$txt['smm_tab_basic'] = 'Основные данные';
$txt['smm_tab_settings'] = 'Конструктор настроек';
$txt['smm_tab_database'] = 'Конструктор таблиц';
$txt['smm_tab_package'] = 'Упаковка';

$txt['smm_name'] = 'Название мода';
$txt['smm_filename'] = 'Имя файла мода';
$txt['smm_filename_subtext'] = 'Латинскими буквами, без пробелов, без расширения!';
$txt['smm_hooks'] = 'Используемые хуки';
$txt['smm_min_search_length'] = 'Введите не менее %d символов';
$txt['smm_hooks_subtext'] = 'Все указанные вами хуки будут сохраняться в базе данных для быстрого доступа при создании новых проектов.';
$txt['smm_mod_version'] = 'Версия мода';
$txt['smm_site_subtext'] = 'Например, ссылка на проект на Гитхабе.';

$txt['smm_tab_settings_desc'] = 'Не всем модам нужны настройки, но если ваш из тех, которым нужны — этот конструктор поможет их создать.';
$txt['smm_mod_title'] = 'Заголовок вкладки в админке';
$txt['smm_mod_title_default'] = 'Супер-пупер мод';
$txt['smm_mod_desc'] = 'Описание мода в админке';
$txt['smm_mod_desc_default'] = 'Описание супер-пупер мода.';

$txt['smm_option_new'] = 'Добавить опцию';
$txt['smm_option_name'] = 'Имя опции (латинскими буквами)';
$txt['smm_option_type'] = 'Тип опции';
$txt['smm_option_types'] = [
	'check' => 'Поле-флажок',
	'text' => 'Текстовое поле',
	'large_text' => 'Текстовая область',
	'select' => 'Список',
	'select-multiple' => 'Список с выбором нескольких значений',
	'int' => 'Ввод целых чисел',
	'float' => 'Ввод дробных чисел',
	'bbc' => 'Выбор форумных тегов',
	'boards' => 'Выбор разделов',
	'password' => 'Поле ввода пароля',
	'permissions' => 'Права доступа для групп',
	'url' => 'Поле для ввода URL-адреса',
	'color' => 'Выбор цвета',
	'date' => 'Выбор даты',
	'datetime-local' => 'Выбор локальной даты',
	'email' => 'Имейл',
	'time' => 'Выбор времени',
	'callback' => 'Кастомный шаблон'
];
$txt['smm_option_default_value'] = 'Значение по умолчанию';
$txt['smm_option_variants'] = 'Возможные значения';
$txt['smm_option_variants_placeholder'] = 'Несколько вариантов, разделённых прямой чертой («|»)';
$txt['smm_option_translations'] = 'Локализация';

$txt['smm_tab_database_desc'] = 'Если вам нужны дополнительные таблицы в базе данных, воспользуйтесь этим конструктором.';
$txt['smm_table_new'] = 'Добавить таблицу';
$txt['smm_table_name'] = 'Имя таблицы (латинскими буквами, без префикса)';
$txt['smm_column_new'] = 'Добавить столбец';
$txt['smm_column_name'] = 'Имя столбца (латинскими буквами)';
$txt['smm_column_type'] = 'Тип столбца';
$txt['smm_column_null'] = 'NULL';
$txt['smm_column_size'] = 'Размер столбца';
$txt['smm_column_auto'] = 'AUTO_INCREMENT';

$txt['smm_license'] = 'Лицензия мода';
$txt['smm_license_own'] = 'Своя лицензия';
$txt['smm_license_name'] = 'Название лицензии';
$txt['smm_license_link'] = 'Ссылка на лицензию';
$txt['smm_make_dir'] = 'Создать отдельную директорию в Sources';
$txt['smm_make_dir_subtext'] = 'Включите эту опцию, если заранее знаете, что ваш мод будет состоять из множества файлов.';
$txt['smm_use_strict_typing'] = 'Использовать строгую типизацию';
$txt['smm_use_strict_typing_subtext'] = 'При выборе этого пункта в заголовок файла мода будет добавлена директива <a class="bbc_link" href="https://www.php.net/manual/ru/language.types.declarations.php#language.types.declarations.strict" target="_blank" rel="nofollow">declare(strict_types=1);</a>.';
$txt['smm_make_template'] = 'Создать заготовку файла шаблона';
$txt['smm_make_script'] = 'Создать заготовку JS-файла';
$txt['smm_make_css'] = 'Создать заготовку CSS-файла';
$txt['smm_make_readme'] = 'Создать файл(ы) readme в дистрибутиве';
$txt['smm_add_copyrights'] = 'Добавить копирайт автора';
$txt['smm_add_copyrights_subtext'] = 'При выборе этого пункта после установки мода на странице ?action=credits появится копирайт автора.';
$txt['smm_min_php_version'] = 'Минимальная версия PHP для установки';

$txt['smm_build'] = 'Собрать';

// Errors
$txt['smm_error_no_name'] = 'Не указано название мода!';
$txt['smm_error_no_filename'] = 'Не указано имя файла!';
$txt['smm_error_no_valid_filename'] = 'Указанное название не соответствует правилам!';
