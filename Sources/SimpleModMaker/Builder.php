<?php

declare(strict_types=1);

/**
 * Builder.php
 *
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.2
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

		file_put_contents(
			$this->path . '/license.txt',
			str_replace(
				'{copyright}', date('Y') . ' ' . $this->skeleton['author'],
				file_get_contents(__DIR__ . '/licenses/' . $this->skeleton['license'] . '.txt')
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

		if (! empty($this->skeleton['title']) || ! empty($this->skeleton['description']) || ! empty($this->skeleton['options']))
			mktree($this->path . '/Themes/default/languages', 0777);

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

			$zipFile->outputAsAttachment($this->snake_name . '_' . $this->skeleton['version'] . '_smf21.zip');
		} catch(ZipException $e) {
			fatal_error($e->getMessage());
		} finally {
			$zipFile->close();
		}

		deltree($this->path . '/readme');
		deltree($this->path . '/Themes');
		deltree($this->path . '/Sources');

		@unlink($this->path . '/package-info.xml');
		@unlink($this->path . '/license.txt');
		@unlink($this->path . '/database.php');
	}

	private function createReadmes()
	{
		if (empty($this->skeleton['make_readme']) || empty($this->skeleton['readmes']))
			return;

		mktree($this->path . '/readme', 0777);

		foreach ($this->skeleton['readmes'] as $lang => $text) {
			file_put_contents(
				$this->path . '/readme/' . $lang . '.txt',
				strtr($text, array(
					'{mod_name}'    => $this->skeleton['name'],
					'{author}'      => $this->skeleton['author'],
					'{description}' => $this->skeleton['description'][$lang] ?? '',
					'{license}'     => $this->license
				))
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
if ((SMF === 'SSI') && ! \$user_info['is_admin'])
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
			'size' => {$column['size']},
XXX . PHP_EOL;

				if (! empty($column['default']))
					$database .= <<<XXX
			'default' => {$this->getDefaultValue($column['default'])},
XXX . PHP_EOL;

				if (in_array($column['type'], ['tinyint', 'int', 'mediumint'])) {
					$database .= <<<XXX
			'unsigned' => true,
XXX . PHP_EOL;

					if (! empty($column['auto']))
						$database .= <<<XXX
			'auto' => true
XXX . PHP_EOL;
				} else {
					if (! empty($column['null']))
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

		foreach ($languages as $lang => $data) {
			foreach ($data as $content) {
				if (! is_file($lang_file = $this->path . '/Themes/default/languages/' . $this->classname . '.' . $lang . '.php'))
					$content = '<?php' . PHP_EOL . PHP_EOL . "/**
 * .$lang.php (language file)
 *
 * @package {$this->skeleton['name']}
 * @author {$this->skeleton['author']} {$this->skeleton['site']}
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

			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;

			$id = $root->appendChild($xml->createElement('id', $this->skeleton['author'] . ':' . $this->classname));
			$root->appendChild($id);

			$name = $root->appendChild($xml->createElement('name', $this->skeleton['name']));
			$root->appendChild($name);

			$version = $root->appendChild($xml->createElement('version', $this->skeleton['version']));
			$root->appendChild($version);

			$type = $root->appendChild($xml->createElement('type', 'modification'));
			$root->appendChild($type);

			$install = $root->appendChild($xml->createElement('install'));
			($install->appendChild($xml->createAttribute('for')))->appendChild($xml->createTextNode('2.1.*'));

			if (! empty($this->skeleton['tables']) || ! empty($this->skeleton['min_php_version']))
				$install->appendChild($xml->createElement('database', 'database.php'));

			if ($this->skeleton['make_readme'] && ! empty($this->skeleton['readmes'])) {
				foreach (array_keys($this->skeleton['readmes']) as $lang) {
					$readme = $install->appendChild($xml->createElement('readme', 'readme/' . $lang . '.txt'));
					($readme->appendChild($xml->createAttribute('parsebbc')))->appendChild($xml->createTextNode('true'));

					if ($lang !== 'english')
						($readme->appendChild($xml->createAttribute('lang')))->appendChild($xml->createTextNode($lang));
				}
			}

			$sources = $install->appendChild($xml->createElement('require-dir'));
			($sources->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('Sources'));
			($sources->appendChild($xml->createAttribute('destination')))->appendChild($xml->createTextNode('$boarddir'));

			$themes = $install->appendChild($xml->createElement('require-dir'));
			($themes->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('Themes'));
			($themes->appendChild($xml->createAttribute('destination')))->appendChild($xml->createTextNode('$boarddir'));

			$filename = empty($this->skeleton['make_dir']) ? $this->skeleton['filename'] : $this->classname . '/Integration';
			$coreclass = $this->skeleton['author'] . '\\' . $this->classname . (empty($this->skeleton['make_dir']) ? '' : '\Integration');

			$hook = $install->appendChild($xml->createElement('hook'));
			($hook->appendChild($xml->createAttribute('hook')))->appendChild($xml->createTextNode('integrate_pre_load'));
			($hook->appendChild($xml->createAttribute('function')))->appendChild($xml->createTextNode($coreclass . '::hooks#'));
			($hook->appendChild($xml->createAttribute('file')))->appendChild($xml->createTextNode('$sourcedir/' . $filename . '.php'));

			if (! empty($this->skeleton['options'])) {
				$redirect = $install->appendChild($xml->createElement('redirect'));
				($redirect->appendChild($xml->createAttribute('url')))->appendChild($xml->createTextNode('?action=admin;area=modsettings;sa=' . $this->snake_name));
				($redirect->appendChild($xml->createAttribute('timeout')))->appendChild($xml->createTextNode('3000'));
			}

			$uninstall = $root->appendChild($xml->createElement('uninstall'));
			($uninstall->appendChild($xml->createAttribute('for')))->appendChild($xml->createTextNode('2.1.*'));

			$hook = $uninstall->appendChild($xml->createElement('hook'));
			($hook->appendChild($xml->createAttribute('hook')))->appendChild($xml->createTextNode('integrate_pre_load'));
			($hook->appendChild($xml->createAttribute('function')))->appendChild($xml->createTextNode($coreclass . '::hooks#'));
			($hook->appendChild($xml->createAttribute('file')))->appendChild($xml->createTextNode('$sourcedir/' . $filename . '.php'));
			($hook->appendChild($xml->createAttribute('reverse')))->appendChild($xml->createTextNode('true'));

			if (! empty($this->skeleton['make_template'])) {
				$template = $uninstall->appendChild($xml->createElement('remove-file'));
				($template->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('$themedir/' . $this->classname . '.template.php'));
			}

			if (! empty($this->skeleton['make_script'])) {
				$scripts = $uninstall->appendChild($xml->createElement('remove-file'));
				($scripts->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('$themedir/scripts/' . $this->snake_name . '.js'));
			}

			if (! empty($this->skeleton['make_css'])) {
				$styles = $uninstall->appendChild($xml->createElement('remove-file'));
				($styles->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('$themedir/css/' . $this->snake_name . '.css'));
			}

			if (empty($this->skeleton['make_dir'])) {
				$core = $uninstall->appendChild($xml->createElement('remove-file'));
				($core->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('$sourcedir/' . $filename . '.php'));
			} else {
				$core = $uninstall->appendChild($xml->createElement('remove-dir'));
				($core->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('$sourcedir/' . $this->classname));
			}

			foreach (array_keys($this->skeleton['readmes']) as $lang) {
				if (is_file($this->path . '/Themes/default/languages/' . $this->classname . '.' . $lang . '.php')) {
					$languages = $uninstall->appendChild($xml->createElement('remove-file'));
					($languages->appendChild($xml->createAttribute('name')))->appendChild($xml->createTextNode('$languagedir/' . $this->classname . '.' . $lang . '.php'));
				}
			}

			$xml_string = $xml->saveXML();
			$xml_string = preg_replace_callback('/^(?:[ ]{2})+/m', function ($m) {
				$spaces = strlen($m[0]);
				$tabs = $spaces / 2;
				return str_repeat("\t", $tabs);
			}, $xml_string);

			file_put_contents($this->path . '/package-info.xml', rtrim($xml_string, PHP_EOL));
		} catch (DOMException $e) {
			fatal_error($e->getMessage());
		}
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

	private function getDefaultValue(string $default): string
	{
		switch ($default) {
			case 'tinyint':
			case 'int':
			case 'mediumint':
				$value = (int) $default;
				break;

			default:
				$value = $default;
		}

		return var_export($value, true);
	}
}
