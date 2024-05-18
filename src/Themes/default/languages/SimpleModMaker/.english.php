<?php

/**
 * @package Simple Mod Maker
 */

$txt['smm_desc'] = '😜 Welcome to the mod\'s page for creating other mods. <strong>Warning</strong>: this mod will not do all the work for you, it will only provide you with a finished application skeleton that you can adapt to your needs. Take the mod code as a tutorial and create, create more interesting mods for SMF!';
$txt['smm_basic'] = 'Basic settings';
$txt['smm_generator'] = 'Generator';

$txt['smm_mod_author'] = 'Developer';
$txt['smm_readme'] = 'Readme file template';
$txt['smm_readme_desc'] = 'The following variables are available:';
$txt['smm_readme_vars'] = [
	'mod_name' => 'The mod name',
	'author' => 'Author name',
	'description' => 'Description',
	'license' => 'License'
];
$txt['smm_readme_default'] = '[center][color=red][size=16pt][b]{mod_name}[/b][/size][/color]
[color=blue][b][size=10pt]By {author}[/size][/b][/color]
[color=green]{description}[/color][/center]

[b]Features:[/b]
[list]
[li]Feature 1[/li]
[li]Feature 2[/li]
[li]Feature 3[/li]
[/list]

[hr][b]It is released under {license}.[/b]';

$txt['smm_add_desc'] = '🐼 The mod wizard will help you to prepare a dummy for further modifications. Carefully fill in the suggested fields. <strong>Note</strong>: a subdirectory with generated files will be created in the <em>Packages</em> directory. Using <a class="bbc_link" href="https://custom.simplemachines.org/index.php?mod=4358">Developer Tools</a> you can immediately test the installation and operation of your modification.';

$txt['smm_tab_basic'] = 'Basic data';
$txt['smm_tab_settings'] = 'Setting Constructor';
$txt['smm_tab_database'] = 'DB Table Constructor';
$txt['smm_tab_tasks'] = 'Task Constructor';
$txt['smm_tab_package'] = 'Packaging';

$txt['smm_name'] = 'Mod title';
$txt['smm_filename'] = 'Mod filename';
$txt['smm_filename_subtext'] = 'In Latin letters, no spaces, no extension!';
$txt['smm_hooks'] = 'Used hooks';
$txt['smm_min_search_length'] = 'Please enter at least %d characters';
$txt['smm_hooks_subtext'] = 'All the hooks you specify will be saved in the database for quick access when creating new projects. You can see the all hook list <a class="bbc_link" href="https://live627.github.io/smf-api-docs-test/hooks/all.html" target="_blank" rel="noopener">here</a>.';
$txt['smm_mod_version'] = 'Mod version';
$txt['smm_site_subtext'] = 'E.g. a link to the project on GitHub.';

$txt['smm_tab_settings_desc'] = 'Not all mods need customization, but if yours is one of those that do, this wizard will help you create it.';
$txt['smm_settings_area'] = 'Where to place settings?';
$txt['smm_settings_area_set'] = ['Nowhere, it\'s fine without them', 'Modification Settings section', 'Separate tab in the "Modification Settings" area', 'Separate section in the admin area'];
$txt['smm_tab_example'] = 'Tab %1$s';
$txt['smm_mod_title_and_desc'] = 'Tab title and the mod description in the admin area';
$txt['smm_mod_title_default'] = 'Super-duper mod';
$txt['smm_mod_desc_default'] = 'Description of the super-duper mod.';

$txt['smm_option'] = 'Option';
$txt['smm_option_new'] = 'Add an option';
$txt['smm_option_name'] = 'Option name';
$txt['smm_option_type'] = 'Option type';
$txt['smm_option_types'] = [
	'check' => 'Checkbox',
	'text' => 'Text field',
	'large_text' => 'Textarea',
	'select' => 'Select box',
	'select-multiple' => 'Select box with multiple selections',
	'int' => 'Integer values',
	'float' => 'Decimal values',
	'bbc' => 'BBCodes',
	'boards' => 'Board select box',
	'password' => 'Password field',
	'permissions' => 'Permissions',
	'url' => 'URL field',
	'color' => 'Color input field',
	'date' => 'Date picker',
	'datetime-local' => 'Datetime picker',
	'email' => 'Email',
	'time' => 'Time field',
	'callback' => 'Custom template'
];
$txt['smm_option_default_value'] = 'Default value';
$txt['smm_option_variants'] = 'Possible values';
$txt['smm_option_variants_placeholder'] = 'Several options separated by a direct line ("|")';
$txt['smm_option_translations'] = 'Localization';

$txt['smm_tab_database_desc'] = 'If you need additional tables in the database, use this wizard.';
$txt['smm_table'] = 'Table';
$txt['smm_table_new'] = 'Add a table';
$txt['smm_table_name'] = 'Table name';
$txt['smm_column'] = 'Column';
$txt['smm_column_new'] = 'Add a column';
$txt['smm_column_name'] = 'Column name';
$txt['smm_column_hint'] = 'Make sure that the name you have chosen is not in the reserved word lists <a class="bbc_link" href="https://dev.mysql.com/doc/refman/8.0/en/keywords.html" target="_blank" rel="noopener">MySQL</a>, <a class="bbc_link" href="https://mariadb.com/kb/en/reserved-words/" target="_blank" rel="noopener">MariaDB</a>, and <a class="bbc_link" href="https://postgrespro.com/docs/postgresql/9.6/sql-keywords-appendix" target="_blank" rel="noopener">PostgreSQL</a>.';
$txt['smm_column_type'] = 'Column type';
$txt['smm_column_null'] = 'NULL';
$txt['smm_column_size'] = 'Column size';
$txt['smm_column_auto'] = 'AUTO_INCREMENT';

$txt['smm_task_run'] = 'Run';
$txt['smm_scheduled_tasks_info'] = 'By default, SMF uses scheduled tasks to run periodically, an example of which you can see right now in the Scheduled Tasks area. You can enable and disable these tasks, and adjust how often they run.';
$txt['smm_scheduled_task'] = 'Scheduled task';
$txt['smm_scheduled_task_slug'] = 'Slug';
$txt['smm_scheduled_task_name'] = 'Task name';
$txt['smm_scheduled_task_description'] = 'Task description';
$txt['smm_scheduled_task_run_set'] = ['daily', 'weekly', 'monthly'];
$txt['smm_scheduled_task_new'] = 'Add a scheduled task';
$txt['smm_background_tasks_info'] = 'Background tasks are used to perform short-term actions (alerts, etc.). Such tasks are deleted from the database after their execution. Examples can be viewed in the Sources/tasks directory.';
$txt['smm_background_task'] = 'Background task';
$txt['smm_background_task_classname'] = 'Class name';
$txt['smm_background_task_run_set'] = ['once', 'daily', 'weekly'];
$txt['smm_background_task_new'] = 'Add a background task';
$txt['smm_legacy_tasks_info'] = 'You do not need to create special tasks. If you need certain code to run every day or every week, we can implement that with hooks.';
$txt['smm_legacy_task'] = 'Simple task';
$txt['smm_legacy_task_method'] = 'Method name';
$txt['smm_legacy_task_run_set'] = ['daily', 'weekly'];
$txt['smm_legacy_task_new'] = 'Add a simple task';

$txt['smm_license'] = 'Mod license';
$txt['smm_license_own'] = 'Own license';
$txt['smm_license_name'] = 'License name';
$txt['smm_license_link'] = 'License link';
$txt['smm_make_dir'] = 'Create a separate directory in Sources';
$txt['smm_make_dir_subtext'] = 'Enable this option if you know in advance that your mod will consist of many files.';
$txt['smm_use_lang_dir'] = 'Use a separate folder for languages';
$txt['smm_make_template'] = 'Create a template file';
$txt['smm_make_script'] = 'Create an empty JS file';
$txt['smm_make_css'] = 'Create an empty CSS file';
$txt['smm_make_readme'] = 'Create readme files in the package';
$txt['smm_add_copyrights'] = 'Add author\'s copyright';
$txt['smm_add_copyrights_subtext'] = 'If you enabled this option after installing the mod, the author\'s copyright will appear on the ?action=credits page.';
$txt['smm_min_php_version'] = 'Minimum required PHP version';
$txt['smm_smf_target_version'] = 'The SMF version that this mod is designed for';

$txt['smm_build'] = 'Build';

// Errors
$txt['smm_error_no_name'] = 'The mod title is not specified!';
$txt['smm_error_no_filename'] = 'The mod filename is not specified!';
$txt['smm_error_no_valid_filename'] = 'The filename does not match the rules!';
$txt['smm_error_option_name_too_long'] = 'The option name should not be longer than 30 characters.';
$txt['smm_error_table_name_too_long'] = 'The table name should not be longer than 64 characters.';
$txt['smm_error_column_name_too_long'] = 'The column name should not be longer than 64 characters.';
