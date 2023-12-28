<?php

declare(strict_types=1);

/**
 * Builder.php
 *
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2023 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.6.1
 */

namespace Bugo\SimpleModMaker;

use DOMException;
use DOMImplementation;
use Exception;
use Phar;
use PharData;

if (! defined('SMF'))
	die('No direct access...');

final class Builder
{
	private array $skeleton;

	private string $classname;

	private string $snake_name;

	private string $path;

	public function __construct(array $options)
	{
		$this->skeleton   = $options['skeleton'];
		$this->classname  = $options['classname'];
		$this->snake_name = $options['snakename'];
		$this->path       = $options['path'];
	}

	public function create(string $content): Builder
	{
		$this->addSecurityCheck($content);

		require_once dirname(__DIR__) . '/Subs-Package.php';

		deltree($this->path);

		mktree($this->path, 0777);

		package_put_contents(
			$this->path . '/license.txt',
			str_replace(
				'{copyright}', date('Y') . ' ' . $this->skeleton['author'],
				package_get_contents(__DIR__ . '/licenses/' . $this->skeleton['license'] . '.txt')
			)
		);

		if (! empty($this->skeleton['make_template']) || ! empty($this->skeleton['callbacks'])) {
			mktree($this->path . '/Themes/default', 0777);

			$template = "<?php\n\n";
			$template .= "function template_my_area()\n";
			$template .= "{\n";
			$template .= "\t// Add your code here\n\n";
			$template .= "\t// Example of using:\n";
			$template .= "\t// loadTemplate('$this->classname');\n";
			$template .= "\t// \$context['sub_template'] = 'my_area';\n";
			$template .= "}\n";

			file_put_contents($this->path . '/Themes/default/' . $this->classname . '.template.php', $template);

			foreach ($this->skeleton['callbacks'] as $callback) {
				$callbackTemplate = "\nfunction template_callback_{$callback}()\n";
				$callbackTemplate .= "{\n";
				$callbackTemplate .= "\t// Add your code here\n";
				$callbackTemplate .= "}\n";

				file_put_contents($this->path . '/Themes/default/' . $this->classname . '.template.php', $callbackTemplate, FILE_APPEND);
			}
		}

		$lang_dir = $this->path . '/Themes/default/languages';
		if (! empty($this->skeleton['title']) || ! empty($this->skeleton['description']) || ! empty($this->skeleton['options']) || ! empty($this->skeleton['scheduled_tasks'])) {
			mktree($lang_dir, 0777);
		}

		if (! empty($this->skeleton['use_lang_dir'])) {
			mktree($lang_dir . '/' . $this->classname, 0777);
			copy(__DIR__ . '/index.php', $lang_dir . '/' . $this->classname . '/index.php');
		}

		if (! empty($this->skeleton['make_script'])) {
			mktree($this->path . '/Themes/default/scripts', 0777);

			package_put_contents($this->path . '/Themes/default/scripts/' . $this->snake_name . '.js', "/* Put your JS here */");
		}

		if (! empty($this->skeleton['make_css'])) {
			mktree($this->path . '/Themes/default/css', 0777);

			package_put_contents($this->path . '/Themes/default/css/' . $this->snake_name . '.css', "/* Put your CSS here */");
		}

		$this->createReadmes();
		$this->createTables();
		$this->createLangs();

		mktree($this->path . '/Sources', 0777);

		if (empty($this->skeleton['make_dir'])) {
			file_put_contents($this->path . '/Sources/' . $this->skeleton['filename'] . '.php', $content, LOCK_EX);

			return $this;
		}

		mktree($this->path . '/Sources/' . $this->classname, 0777);

		copy(__DIR__ . '/index.php', $this->path . '/Sources/' . $this->classname . '/index.php');

		file_put_contents($this->path . '/Sources/' . $this->classname . '/Integration.php', $content, LOCK_EX);

		return $this;
	}

	public function createTasks(array $tasks): Builder
	{
		foreach ($tasks as $task) {
			$this->addSecurityCheck($task['content']);

			mktree($this->path . '/Sources/' . $this->classname . '/Tasks', 0777);

			copy(__DIR__ . '/index.php', $this->path . '/Sources/' . $this->classname . '/Tasks/index.php');

			file_put_contents($this->path . '/Sources/' . $this->classname . '/Tasks/' . $task['filename'], $task['content'], LOCK_EX);
		}

		return $this;
	}

	/**
	 * @throws Exception
	 */
	public function createPackage(): void
	{
		$this->preparePackageInfo();

		$filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(5)) . '_smf21';

		try {
			$phar = new PharData($filename . '.tmp');
			$phar->buildFromDirectory($this->path);
			$phar->convertToData(Phar::ZIP, Phar::NONE, 'zip');

			$this->download($filename . '.zip');
		} catch (Exception $e) {
			fatal_error($e->getMessage());
		}
	}

	private function download(string $file): void
	{
		if (file_exists($file) === false)
			return;

		ob_end_clean();

		$pretty_name = $this->snake_name . '_' . $this->skeleton['version'] . '_smf21.zip';

		header_remove('content-encoding');
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $pretty_name);
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));

		if ($fd = fopen($file, 'rb')) {
			while (! feof($fd))
				print fread($fd, 1024);

			fclose($fd);
		}

		unlink($file);
		unlink(str_replace('.zip', '.tmp', $file));

		exit;
	}

	private function createReadmes(): void
	{
		if (empty($this->skeleton['make_readme']) || empty($this->skeleton['readmes']))
			return;

		mktree($this->path . '/readme', 0777);

		foreach ($this->skeleton['readmes'] as $lang => $text) {
			package_put_contents(
				$this->path . '/readme/' . $lang . '.txt',
				strtr($text, [
					'{mod_name}'    => $this->skeleton['name'],
					'{author}'      => $this->skeleton['author'],
					'{description}' => $this->skeleton['description'][$lang] ?? '',
					'{license}'     => $this->skeleton['license_data']['full_name']
				])
			);
		}
	}

	private function createTables(): void
	{
		if (empty($this->skeleton['tables']) && empty($this->skeleton['min_php_version']) && empty($this->skeleton['scheduled_tasks']))
			return;

		$database = "<?php\n\n";
		$database .= "if (file_exists(dirname(__FILE__) . '/SSI.php') && ! defined('SMF'))\n";
		$database .= "\trequire_once(dirname(__FILE__) . '/SSI.php');\n";
		$database .= "elseif (! defined('SMF'))\n";
		$database .= "\tdie('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');";
		$database .= "\n\n";

		if (! empty($this->skeleton['min_php_version'])) {
			$minPhpVersion = $this->skeleton['min_php_version'];
			$database .= "if (version_compare(PHP_VERSION, '{$minPhpVersion}', '<')) {\n";
			$database .= "\tdie('This mod needs PHP {$minPhpVersion} or greater. You will not be able to install/use this mod. Please, contact your host and ask for a php upgrade.');\n";
			$database .= "}\n\n";
		}

		if (! empty($this->skeleton['tables'])) {
			$database .= "if (SMF === 'SSI' && ! \$user_info['is_admin'])\n";
			$database .= "\tdie('Admin privileges required.');";
			$database .= "\n\n";
		}

		foreach ($this->skeleton['tables'] as $table) {
			$database .= "\$tables[] = [\n";
			$database .= "\t'name' => '{$table['name']}',\n";
			$database .= "\t'columns' => [\n";

			$table_index = false;

			foreach ($table['columns'] as $column) {
				if (! empty($column['auto'])) {
					$table_index = $column['name'];
				}

				$database .= "\t\t[\n";
				$database .= "\t\t\t'name' => '{$column['name']}',\n";
				$database .= "\t\t\t'type' => '{$column['type']}',\n";

				if (! in_array($column['type'], ['text', 'mediumtext'])) {
					if (! empty($column['size'])) {
						$database .= "\t\t\t'size' => {$column['size']},\n";
					}

					if (empty($column['auto']) && strlen($column['default'])) {
						$default = $this->getDefaultValue($column);
						$database .= "\t\t\t'default' => {$default},\n";
					}
				}

				if (in_array($column['type'], ['tinyint', 'int', 'mediumint'])) {
					$database .= "\t\t\t'unsigned' => true,\n";

					if (! empty($column['auto'])) {
						$database .= "\t\t\t'auto' => true,\n";
					}
				} elseif (! empty($column['null'])) {
					$database .= "\t\t\t'null' => true,\n";
				}

				$database .= "\t\t],\n";
			}

			$database .= "\t],\n";

			if (! empty($table_index)) {
				$database .= "\t'indexes' => [\n";
				$database .= "\t\t[\n";
				$database .= "\t\t\t'type' => 'primary',\n";
				$database .= "\t\t\t'columns' => ['{$table_index}'],\n";
				$database .= "\t\t]\n";
				$database .= "\t],\n";
			}

			$database .= "];\n\n";
		}

		if (! empty($this->skeleton['tables'])) {
			$database .= "foreach (\$tables as \$table) {\n";
			$database .= "\t\$smcFunc['db_create_table']('{db_prefix}' . \$table['name'], \$table['columns'], \$table['indexes']);\n";
			$database .= "}\n\n";
		}

		$this->addScheduledTasks($database);

		$database .= "if (SMF === 'SSI')\n";
		$database .= "\techo 'Database changes are complete!';\n";

		package_put_contents($this->path . '/database.php', $database);
	}

	private function addScheduledTasks(string &$database): void
	{
		if (empty($this->skeleton['scheduled_tasks']))
			return;

		foreach ($this->skeleton['scheduled_tasks'] as $task) {
			switch ($task['regularity']) {
				case 0:
					$regularity = 1;
					$unit = 'd';
					break;

				case 1:
					$regularity = 1;
					$unit = 'w';
					break;

				default:
					$regularity = 4;
					$unit = 'w';
			}

			$database .= "\$smcFunc['db_insert']('',\n";
			$database .= "\t'{db_prefix}scheduled_tasks',\n";
			$database .= "\t[\n";
			$database .= "\t\t'next_time'       => 'int',\n";
			$database .= "\t\t'time_offset'     => 'int',\n";
			$database .= "\t\t'time_regularity' => 'int',\n";
			$database .= "\t\t'time_unit'       => 'string',\n";
			$database .= "\t\t'disabled'        => 'int',\n";
			$database .= "\t\t'task'            => 'string',\n";
			$database .= "\t\t'callable'        => 'string'\n";
			$database .= "\t],\n";
			$database .= "\t[\n";
			$database .= "\t\tstrtotime('tomorrow'),\n";
			$database .= "\t\t0,\n";
			$database .= "\t\t{$regularity},\n";
			$database .= "\t\t'{$unit}',\n";
			$database .= "\t\t0,\n";
			$database .= "\t\t'{$task['slug']}',\n";
			$database .= "\t\t'{$task['callable']}'\n";
			$database .= "\t],\n";
			$database .= "\t['id_task']\n";
			$database .= ");\n\n";
		}
	}

	private function getDefaultValue(array $column): ?string
	{
		switch ($column['type']) {
			case 'tinyint':
			case 'int':
			case 'mediumint':
				$value = (int) $column['default'];
				break;
			default:
				$value = $column['default'];
		}

		return var_export($value, true);
	}

	private function createLangs(): void
	{
		global $txt;

		$languages = [];

		foreach ($this->skeleton['title'] as $lang => $value) {
			$title = $value ?: $this->skeleton['name'];
			$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_title'] = '$title';";
		}

		foreach ($this->skeleton['description'] as $lang => $value) {
			$description = $value ?: '';
			$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_description'] = '$description';";

			if ($this->skeleton['settings_area'] === 3) {
				loadLanguage('SimpleModMaker/', $lang);

				$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_section1_title'] = '" . sprintf($txt['smm_tab_example'], 1) . "';";
				$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_section2_title'] = '" . sprintf($txt['smm_tab_example'], 2) . "';";
			}
		}

		foreach ($this->skeleton['options'] as $option) {
			foreach ($option['translations'] as $lang => $value) {
				$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_{$option['name']}'] = '$value';";

				if (in_array($option['type'], ['select-multiple', 'select'])) {
					if (! empty($option['variants'])) {
						$variants  = explode('|', $option['variants']);
						$variants = "'" . implode("','", $variants) . "'";

						$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_{$option['name']}_set'] = [$variants];";
					}
				}
			}
		}

		foreach ($this->skeleton['scheduled_tasks'] as $task) {
			foreach ($task['names'] as $lang => $value) {
				$languages[$lang][] = PHP_EOL . "\$txt['scheduled_task_{$task['slug']}'] = '$value';";
			}

			foreach ($task['descriptions'] as $lang => $value) {
				$languages[$lang][] = PHP_EOL . "\$txt['scheduled_task_desc_{$task['slug']}'] = '$value';";
			}
		}

		$lang_dir = $this->path . '/Themes/default/languages/' . $this->classname . ($this->skeleton['use_lang_dir'] ? '/.' : '.');

		foreach ($languages as $lang => $data) {
			foreach ($data as $content) {
				if (! is_file($lang_file = $lang_dir . $lang . '.php')) {
					$header = "<?php\n\n";
					$header .= "/**\n";
					$header .= " * @package {$this->skeleton['name']}\n";
					$header .= " */\n";
					$content = $header . $content;
				}

				file_put_contents($lang_file, $content, FILE_APPEND | LOCK_EX);
			}
		}
	}

	private function preparePackageInfo(): void
	{
		try {
			$imp = new DOMImplementation();
			$dtd = $imp->createDocumentType('package-info', '', 'http://www.simplemachines.org/xml/package-info');
			$xml = $imp->createDocument('', '', $dtd);
			$xml->appendChild($xml->createComment(' Generated by ' . SMM_NAME . ' '));

			$root = $xml->createElementNS('http://www.simplemachines.org/xml/package-info', 'package-info');
			$root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:smf', 'http://www.simplemachines.org/');
			$xml->appendChild($root);

			$xml->preserveWhiteSpace = true;
			$xml->formatOutput = true;

			$data = $this->getProperData();

			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$root->appendChild($xml->createElement('empty'));

					$element = $root->appendChild($xml->createElement($key));

					foreach ($value as $k => $v) {
						if (is_array($v)) {
							if ($k === '@attributes') {
								foreach ($v as $i => $j) {
									($element->appendChild($xml->createAttribute($i)))->appendChild($xml->createTextNode($j));
								}
							} else {
								foreach ($v as $r => $s) {
									$e = $element->appendChild($xml->createElement($k, is_string($r) ? $r : ''));
									foreach ($s as $x => $y) {
										($e->appendChild($xml->createAttribute($x)))->appendChild($xml->createTextNode($y));
									}
								}
							}
						} else {
							$element->appendChild($xml->createElement($k, $v));
						}
					}
				} else {
					$element = $root->appendChild($xml->createElement($key, $value));
					$root->appendChild($element);
				}
			}

			$xml_string = $xml->saveXML();

			// Make empty string
			$xml_string = str_replace('  <empty/>', '', $xml_string);

			// Replace spaces with tabs
			$xml_string = preg_replace_callback('/^(?: {2})+/m', function ($m) {
				$spaces = strlen($m[0]);
				$tabs = $spaces / 2;
				return str_repeat("\t", $tabs);
			}, $xml_string);

			package_put_contents($this->path . '/package-info.xml', rtrim($xml_string, PHP_EOL));
		} catch (DOMException $e) {
			fatal_error($e->getMessage());
		}
	}

	private function getProperData(): array
	{
		$data = [
			'id'      => $this->skeleton['author'] . ':' . $this->classname,
			'name'    => $this->skeleton['name'],
			'version' => $this->skeleton['version'],
			'type'    => 'modification',
		];

		$filename  = empty($this->skeleton['make_dir']) ? $this->skeleton['filename'] : $this->classname . '/Integration';
		$coreclass = $this->skeleton['author'] . '\\' . $this->classname . (empty($this->skeleton['make_dir']) ? '' : '\Integration');
		$languages = array_keys($this->skeleton['readmes']);

		$data['install'] = [
			'@attributes' => [
				'for' => '2.1.*',
			],
			'hook' => [
				[
					'hook' => 'integrate_pre_load',
					'function' => $coreclass . '::hooks#',
					'file' => '$sourcedir/' . $filename . '.php',
				],
			],
		];

		$data['uninstall'] = [
			'@attributes' => [
				'for' => '2.1.*',
			],
			'hook' => [
				array_merge((array) $data['install']['hook'][0], [
					'reverse' => 'true',
				]),
			],
		];

		if (is_file($this->path . '/database.php'))
			$data['install']['database'] = 'database.php';

		if ($this->skeleton['make_readme']) {
			foreach ($languages as $lang) {
				$readmeData = [
					'parsebbc' => 'true'
				];

				if ($lang !== 'english') {
					$readmeData['lang'] = $lang;
				}

				$data['install']['readme']["readme/{$lang}.txt"] = $readmeData;
			}
		}

		if (empty($this->skeleton['make_dir'])) {
			$data['install']['require-file']['Adding main source file'] = [
				'name' => 'Sources/' . $this->skeleton['filename'] . '.php',
				'destination' => '$sourcedir',
			];

			$data['uninstall']['remove-file']['Removing main source file'] = [
				'name' => '$sourcedir/' . $filename . '.php',
			];
		} else {
			$data['install']['require-dir']['Adding main source files'] = [
				'name' => 'Sources/' . $this->classname,
				'destination' => '$sourcedir',
			];

			$data['uninstall']['remove-dir']['Removing main source files'] = [
				'name' => '$sourcedir/' . $this->classname,
			];
		}

		if (! empty($this->skeleton['make_template'])) {
			$data['install']['require-file']['Adding main template file'] = [
				'name' => 'Themes/default/' . $this->classname . '.template.php',
				'destination' => '$themedir',
			];

			$data['uninstall']['remove-file']['Removing main template file'] = [
				'name' => '$themedir/' . $this->classname . '.template.php',
			];
		}

		if (! empty($this->skeleton['make_script'])) {
			$data['install']['require-file']['Adding JS file'] = [
				'name' => 'Themes/default/scripts/' . $this->snake_name . '.js',
				'destination' => '$themedir/scripts',
			];

			$data['uninstall']['remove-file']['Removing JS file'] = [
				'name' => '$themedir/scripts/' . $this->snake_name . '.js',
			];
		}

		if (! empty($this->skeleton['make_css'])) {
			$data['install']['require-file']['Adding CSS file'] = [
				'name' => 'Themes/default/css/' . $this->snake_name . '.css',
				'destination' => '$themedir/css',
			];

			$data['uninstall']['remove-file']['Removing CSS file'] = [
				'name' => '$themedir/css/' . $this->snake_name . '.css',
			];
		}

		if (empty($this->skeleton['use_lang_dir'])) {
			$languagedir = '$languagedir';

			foreach ($languages as $lang) {
				$language_file = 'Themes/default/languages/' . $this->classname . '.' . $lang . '.php';
				$language_filepath = $this->path . '/' . $language_file;

				if (is_file($language_filepath)) {
					$data['install']['require-file']['Adding ' . ucfirst($lang) . ' language file'] = [
						'name' => $language_file,
						'destination' => $languagedir,
					];

					$data['uninstall']['remove-file']['Removing ' . ucfirst($lang) . ' language file'] = [
						'name' => $languagedir . '/' . $this->classname . '.' . $lang . '.php',
					];
				}
			}
		} else {
			$data['install']['require-dir']['Adding language files'] = [
				'name' => 'Themes/default/languages/' . $this->classname,
				'destination' => '$languagedir',
			];

			$data['uninstall']['remove-dir']['Removing language files'] = [
				'name' => '$languagedir/' . $this->classname,
			];
		}

		if (! empty($this->skeleton['options'])) {
			$data['install']['redirect'][] = [
				'url' => '?action=admin;area=modsettings;sa=' . $this->snake_name,
				'timeout' => '3000',
			];
		}

		return $data;
	}

	private function addSecurityCheck(string &$content): void
	{
		$message = "if (!defined('SMF'))
	die('No direct access...');

/**
 * Generated by " . SMM_NAME . "
 */";

		$content = str_replace('/**' . "\n" . ' * Generated by ' . SMM_NAME . "\n" . ' */', $message, $content);
	}
}
