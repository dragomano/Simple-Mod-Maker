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
 * @version 0.5
 */

namespace Bugo\SimpleModMaker;

use DateTimeImmutable;
use DOMException;
use DOMImplementation;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Constants\ZipOptions;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Symfony\Component\Finder\Finder;

if (! defined('SMF'))
	die('No direct access...');

final class Builder
{
	private array $skeleton;

	private string $classname;

	private string $snake_name;

	private string $license;

	private string $path;

	public function __construct(array $options)
	{
		$this->skeleton   = $options['skeleton'];
		$this->classname  = $options['classname'];
		$this->snake_name = $options['snakename'];
		$this->license    = $options['license'];
		$this->path       = $options['path'];
	}

	public function create(string $content): Builder
	{
		$this->addSecurityCheck($content);

		require_once dirname(__DIR__) . '/Subs-Package.php';

		deltree($this->path . '/readme');
		deltree($this->path . '/Themes');
		deltree($this->path . '/Sources');

		@unlink($this->path . '/package-info.xml');
		@unlink($this->path . '/license.txt');
		@unlink($this->path . '/database.php');

		file_put_contents(
			$this->path . '/license.txt',
			str_replace(
				'{copyright}', date('Y') . ' ' . $this->skeleton['author'],
				file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'licenses' . DIRECTORY_SEPARATOR . $this->skeleton['license'] . '.txt')
			)
		);

		if (! empty($this->skeleton['make_template']) || ! empty($this->skeleton['callbacks'])) {
			mktree($this->path . '/Themes/default', 0777);

			file_put_contents($this->path . '/Themes/default/' . $this->classname . '.template.php', "<?php

function template_my_area()
{
	// Add your code here

	// Example of using:
	// loadTemplate('$this->classname');
	// \$context['sub_template'] = 'my_area';
}" . PHP_EOL);

			if (! empty($this->skeleton['callbacks'])) {
				foreach ($this->skeleton['callbacks'] as $callback) {
					file_put_contents($this->path . '/Themes/default/' . $this->classname . '.template.php', str_replace('{callback}', $callback, "
function template_callback_{callback}()
{
	// Add your code here
}") . PHP_EOL, FILE_APPEND);
				}
			}
		}

		$lang_dir = $this->path . '/Themes/default/languages';
		if (! empty($this->skeleton['title']) || ! empty($this->skeleton['description']) || ! empty($this->skeleton['options'])) {
			mktree($lang_dir, 0777);
		}

		if (! empty($this->skeleton['use_lang_dir'])) {
			mktree($lang_dir . '/' . $this->classname, 0777);
			copy(__DIR__ . '/index.php', $lang_dir . '/' . $this->classname . '/index.php');
		}

		if (! empty($this->skeleton['make_script'])) {
			mktree($this->path . '/Themes/default/scripts', 0777);

			file_put_contents($this->path . '/Themes/default/scripts/' . $this->snake_name . '.js', "/* Put your JS here */");
		}

		if (! empty($this->skeleton['make_css'])) {
			mktree($this->path . '/Themes/default/css', 0777);

			file_put_contents($this->path . '/Themes/default/css/' . $this->snake_name . '.css', "/* Put your CSS here */");
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

	public function createPackage()
	{
		$this->preparePackageInfo();

		$zipFile = new ZipFile();

		try {
			$finder = new Finder();
			$finder
				->files()
				->notName('index.php')
				->notName('*.zip')
				->in($this->path);

			$zipFile->addFromFinder($finder, [
				ZipOptions::COMPRESSION_METHOD => ZipCompressionMethod::DEFLATED,
				ZipOptions::MODIFIED_TIME => new DateTimeImmutable('-1 day 5 min')
			]);

			$zipFile->addDirRecursive($this->path . '/Sources', 'Sources');

			if (is_dir($this->path . '/Themes'))
				$zipFile->addDirRecursive($this->path . '/Themes', 'Themes');

			$zipFile->outputAsAttachment($this->snake_name . '_' . $this->skeleton['version'] . '_smf21.zip');
		} catch (ZipException $e) {
			fatal_error($e->getMessage());
		} finally {
			$zipFile->close();
		}
	}

	private function createReadmes()
	{
		if (empty($this->skeleton['make_readme']) || empty($this->skeleton['readmes']))
			return;

		mktree($this->path . '/readme', 0777);

		foreach ($this->skeleton['readmes'] as $lang => $text) {
			file_put_contents(
				$this->path . '/readme/' . $lang . '.txt',
				strtr($text, [
					'{mod_name}'    => $this->skeleton['name'],
					'{author}'      => $this->skeleton['author'],
					'{description}' => $this->skeleton['description'][$lang] ?? '',
					'{license}'     => $this->license
				])
			);
		}
	}

	private function createTables()
	{
		if (empty($this->skeleton['tables']) && empty($this->skeleton['min_php_version']))
			return;

		$database = <<<XXX
<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && ! defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(! defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');
XXX . PHP_EOL . PHP_EOL;

		if (! empty($this->skeleton['min_php_version'])) {
			$database .= <<<XXX
if (version_compare(PHP_VERSION, '{$this->skeleton['min_php_version']}', '<'))
	die('This mod needs PHP {$this->skeleton['min_php_version']} or greater. You will not be able to install/use this mod. Please, contact your host and ask for a php upgrade.');
XXX . PHP_EOL . PHP_EOL;
		}

		if (! empty($this->skeleton['tables'])) {
			$database .= <<<XXX
if (SMF === 'SSI' && ! \$user_info['is_admin'])
	die('Admin privileges required.');
XXX . PHP_EOL . PHP_EOL;
		}

		foreach ($this->skeleton['tables'] as $table) {
			$database .= <<<XXX
\$tables[] = array(
	'name' => '{$table['name']}',
	'columns' => array(
XXX . PHP_EOL;

			$table_index = false;

			foreach ($table['columns'] as $column) {
				if (! empty($column['auto']))
					$table_index = $column['name'];

				$database .= <<<XXX
		array(
			'name' => '{$column['name']}',
			'type' => '{$column['type']}',
XXX . PHP_EOL;

				if (! in_array($column['type'], ['text', 'mediumtext'])) {
					if (! empty($column['size']))
						$database .= <<<XXX
			'size' => {$column['size']},
XXX . PHP_EOL;

					if (empty($column['auto']) && strlen($column['default']))
						$database .= <<<XXX
			'default' => {$this->getDefaultValue($column)},
XXX . PHP_EOL;
				}

				if (in_array($column['type'], ['tinyint', 'int', 'mediumint'])) {
					$database .= <<<XXX
			'unsigned' => true,
XXX . PHP_EOL;

					if (! empty($column['auto']))
						$database .= <<<XXX
			'auto' => true
XXX . PHP_EOL;
				} elseif (! empty($column['null'])) {
					$database .= <<<XXX
			'null' => true
XXX . PHP_EOL;
				}

				$database .= <<<XXX
		),
XXX . PHP_EOL;
			}

			$database .= <<<XXX
	),
XXX . PHP_EOL;

			if (! empty($table_index))
				$database .= <<<XXX
	'indexes' => array(
		array(
			'type' => 'primary',
			'columns' => array('$table_index')
		)
	)
XXX . PHP_EOL;

			$database .= <<<XXX
);

XXX . PHP_EOL;
		}

		if (! empty($this->skeleton['tables'])) {
			$database .= <<<XXX
foreach (\$tables as \$table) {
	\$smcFunc['db_create_table']('{db_prefix}' . \$table['name'], \$table['columns'], \$table['indexes']);
}

if (SMF === 'SSI')
	echo 'Database changes are complete!';
XXX . PHP_EOL;
		}

		file_put_contents($this->path . '/database.php', $database);
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

	private function createLangs()
	{
		$languages = [];

		foreach ($this->skeleton['title'] as $lang => $value) {
			$title = $value ?: $this->skeleton['name'];
			$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_title'] = '$title';";
		}

		foreach ($this->skeleton['description'] as $lang => $value) {
			$description = $value ?: '';
			$languages[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_description'] = '$description';";
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

		$lang_dir = $this->path . '/Themes/default/languages/' . $this->classname . ($this->skeleton['use_lang_dir'] ? '/.' : '.');

		foreach ($languages as $lang => $data) {
			foreach ($data as $content) {
				if (! is_file($lang_file = $lang_dir . $lang . '.php'))
					$content = '<?php' . PHP_EOL . PHP_EOL . "/**
 * @package {$this->skeleton['name']}
*/" . PHP_EOL . $content;

				file_put_contents($lang_file, $content, FILE_APPEND | LOCK_EX);
			}
		}
	}

	private function preparePackageInfo()
	{
		try {
			$imp = new DOMImplementation();
			$dtd = $imp->createDocumentType('package-info', '', 'http://www.simplemachines.org/xml/package-info');
			$xml = $imp->createDocument('', '', $dtd);
			$xml->appendChild($xml->createComment(' Generated by Simple Mod Maker '));

			$root = $xml->createElementNS('http://www.simplemachines.org/xml/package-info', 'package-info');
			$root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:smf', 'http://www.simplemachines.org/');
			$xml->appendChild($root);

			$xml->preserveWhiteSpace = true;
			$xml->formatOutput = true;

			$data = $this->getProperData();

			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$root->appendChild($root->appendChild($xml->createElement('empty')));

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

			file_put_contents($this->path . '/package-info.xml', rtrim($xml_string, PHP_EOL));
		} catch (DOMException $e) {
			fatal_error($e->getMessage());
		}
	}

	private function getProperData(): array
	{
		$data = [
			'id' => $this->skeleton['author'] . ':' . $this->classname,
			'name' => $this->skeleton['name'],
			'version' => $this->skeleton['version'],
			'type' => 'modification',
		];

		$data['install'] = [
			'@attributes' => [
				'for' => '2.1.*',
			]
		];

		if (! empty($this->skeleton['tables']) || ! empty($this->skeleton['min_php_version']))
			$data['install']['database'] = 'database.php';

		if ($this->skeleton['make_readme'] && ! empty($this->skeleton['readmes'])) {
			foreach (array_keys($this->skeleton['readmes']) as $lang) {
				$data['install']['readme']['readme/' . $lang . '.txt'] = $lang !== 'english' ? [
					'parsebbc' => 'true',
					'lang' => $lang,
				] : [
					'parsebbc' => 'true',
				];
			}
		}

		$data['install']['require-dir'] = [
			[
				'name' => 'Sources',
				'destination' => '$boarddir',
			],
		];

		if (is_dir($this->path . '/Themes')) {
			$data['install']['require-dir'][] = [
				'name' => 'Themes',
				'destination' => '$boarddir',
			];
		}

		$filename = empty($this->skeleton['make_dir']) ? $this->skeleton['filename'] : $this->classname . '/Integration';
		$coreclass = $this->skeleton['author'] . '\\' . $this->classname . (empty($this->skeleton['make_dir']) ? '' : '\Integration');

		$data['install']['hook'][] = [
			'hook' => 'integrate_pre_load',
			'function' => $coreclass . '::hooks#',
			'file' => '$sourcedir/' . $filename . '.php',
		];

		if (! empty($this->skeleton['options'])) {
			$data['install']['redirect'] = [
				'url' => '?action=admin;area=modsettings;sa=' . $this->snake_name,
				'timeout' => '3000',
			];
		}

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

		if (! empty($this->skeleton['make_template'])) {
			$data['uninstall']['remove-file'][] = [
				'name' => '$themedir/' . $this->classname . '.template.php',
			];
		}

		if (! empty($this->skeleton['make_script'])) {
			$data['uninstall']['remove-file'][] = [
				'name' => '$themedir/scripts/' . $this->snake_name . '.js',
			];
		}

		if (! empty($this->skeleton['make_css'])) {
			$data['uninstall']['remove-file'][] = [
				'name' => '$themedir/css/' . $this->snake_name . '.css',
			];
		}

		foreach (array_keys($this->skeleton['readmes']) as $lang) {
			if (is_file($this->path . '/Themes/default/languages/' . $this->classname . '.' . $lang . '.php')) {
				$data['uninstall']['remove-file'][] = [
					'name' => '$languagedir/' . $this->classname . '.' . $lang . '.php',
				];
			}
		}

		if (empty($this->skeleton['make_dir'])) {
			$data['uninstall']['remove-file'][] = [
				'name' => '$sourcedir/' . $filename . '.php',
			];
		} else {
			$data['uninstall']['remove-dir'][] = [
				'name' => '$sourcedir/' . $this->classname,
			];
		}

		if (! empty($this->skeleton['use_lang_dir'])) {
			$data['uninstall']['remove-dir'][] = [
				'name' => '$languagedir/' . $this->classname,
			];
		}

		// Compatibility with Developer Tools
		/*$data['devtools'] = [
			'packagename' => '{CUSTOMIZATION_NAME}_{VERSION}',
		];*/

		return $data;
	}

	private function addSecurityCheck(string &$content)
	{
		$message = <<<XXX
	if (! defined('SMF'))
		die('No direct access...');

	/**
	 * Generated by Simple Mod Maker
	 */
	XXX;

		$content = str_replace('/**
 * Generated by Simple Mod Maker
 */', $message, $content);
	}
}
