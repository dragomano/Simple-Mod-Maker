<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.0
 */

namespace Bugo\SimpleModMaker;

use DOMException;
use DOMImplementation;
use Exception;
use InvalidArgumentException;
use Phar;
use PharData;
use Throwable;

if (! defined('SMF'))
	die('No direct access...');

final class Builder
{
	/**
	 * The runtime check that separates the code branches of a package built
	 * for both SMF versions at once.
	 */
	private const SMF3_CHECK = "version_compare(SMF_VERSION, '3.0', '>=')";

	private array $skeleton;

	/**
	 * @var list<string>
	 */
	private array $created_languages = [];

	private ?string $archive = null;

	private bool $completed = false;

	private readonly string $classname;

	private readonly string $snake_name;

	private readonly string $path;

	/**
	 * @var list<string>
	 */
	private readonly array $allowed_bases;
	/**
	 * @param array{
	 *     skeleton: array,
	 *     classname: string,
	 *     snakename: string,
	 *     path: string
	 * } $options
	 * @throws InvalidArgumentException
	 */
	public function __construct(array $options)
	{
		if (! isset($options['skeleton'], $options['classname'], $options['snakename'], $options['path'])) {
			throw new InvalidArgumentException('Missing required options.');
		}

		$this->skeleton      = $options['skeleton'];
		$this->classname     = $options['classname'];
		$this->snake_name    = $options['snakename'];
		$this->path          = $options['path'];
		$this->allowed_bases = $this->resolveAllowedBases($options);
	}

	public function create(string $content): Builder
	{
		require_once dirname(__DIR__) . '/Subs-Package.php';

		try {
			$this->addSecurityCheck($content);
			$this->assertPathWithinAllowedBase();

			deltree($this->path);

			$this->makeDir($this->path);

			$this->writePackageFile(
				$this->path . '/license.txt',
				str_replace(
					'{copyright}', date('Y') . ' ' . $this->skeleton['author'],
					package_get_contents(__DIR__ . '/licenses/' . $this->skeleton['license'] . '.txt')
				)
			);

			if (TemplateUtil::needsTemplate($this->skeleton)) {
				$this->makeDir($this->path . '/Themes/default');

				$template = "<?php\n\n";
				$template .= "function template_my_area()\n";
				$template .= "{\n";
				$template .= "\t// Add your code here\n\n";
				$template .= "\t// Example of using:\n";
				$template .= "\t// loadTemplate('$this->classname');\n";
				$template .= "\t// \$context['sub_template'] = 'my_area';\n";
				$template .= "}\n";

				$this->writeFile($this->path . '/Themes/default/' . $this->classname . '.template.php', $template);

				foreach ($this->skeleton['callbacks'] as $callback) {
					$callbackTemplate = "\nfunction template_callback_$callback()\n";
					$callbackTemplate .= "{\n";
					$callbackTemplate .= "\t// Add your code here\n";
					$callbackTemplate .= "}\n";

					$this->writeFile(
						$this->path . '/Themes/default/' . $this->classname . '.template.php',
						$callbackTemplate,
						FILE_APPEND | LOCK_EX
					);
				}
			}

			$lang_dir = $this->path . '/Themes/default/languages';
			if ($this->needsLangFiles()) {
				$this->makeDir($lang_dir);
			}

			if (! empty($this->skeleton['use_lang_dir'])) {
				$this->makeDir($lang_dir . '/' . $this->classname);
				$this->copyFile(__DIR__ . '/index.php', $lang_dir . '/' . $this->classname . '/index.php');
			}

			if (! empty($this->skeleton['make_script'])) {
				$this->makeDir($this->path . '/Themes/default/scripts');

				$this->writePackageFile(
					$this->path . '/Themes/default/scripts/' . $this->snake_name . '.js',
					"/* Put your JS here */"
				);
			}

			if (! empty($this->skeleton['make_css'])) {
				$this->makeDir($this->path . '/Themes/default/css');

				$this->writePackageFile(
					$this->path . '/Themes/default/css/' . $this->snake_name . '.css',
					"/* Put your CSS here */"
				);
			}

			$this->createReadmes();
			$this->createTables();
			$this->createUninstallScripts();
			$this->createLangs();

			$this->makeDir($this->path . '/Sources');

			if (empty($this->skeleton['make_dir'])) {
				$this->writeFile($this->path . '/Sources/' . $this->skeleton['filename'] . '.php', $content);
			} else {
				$this->makeDir($this->path . '/Sources/' . $this->classname);

				$this->copyFile(__DIR__ . '/index.php', $this->path . '/Sources/' . $this->classname . '/index.php');

				$this->writeFile($this->path . '/Sources/' . $this->classname . '/Integration.php', $content);
			}
		} catch (Throwable $e) {
			// A failed build must not leave partial files behind
			$this->removeWorkingDir();

			throw $e;
		}

		return $this;
	}

	public function createTasks(array $tasks): Builder
	{
		require_once dirname(__DIR__) . '/Subs-Package.php';

		try {
			$this->assertPathWithinAllowedBase();

			foreach ($tasks as $task) {
				$this->addSecurityCheck($task['content']);

				$this->makeDir($this->path . '/Sources/' . $this->classname . '/Tasks');

				$this->copyFile(__DIR__ . '/index.php', $this->path . '/Sources/' . $this->classname . '/Tasks/index.php');

				$this->writeFile(
					$this->path . '/Sources/' . $this->classname . '/Tasks/' . $task['filename'],
					$task['content']
				);
			}
		} catch (Throwable $e) {
			$this->removeWorkingDir();

			throw $e;
		}

		return $this;
	}

	public function createPackage(): void
	{
		require_once dirname(__DIR__) . '/Subs-Package.php';

		// The transfer can be interrupted at any moment, so the temporary
		// archive files are released by the shutdown handler in any case
		register_shutdown_function(function (): void {
			$this->removeArchive();

			if (! $this->completed) {
				$this->removeWorkingDir();
			}
		});

		try {
			$this->preparePackageInfo();

			$archive = $this->createArchive();

			// The package is built, so the working folder remains in Packages
			// and allows to install the mod right away without a new download
			$this->completed = true;

			$this->download($archive);
		} catch (Throwable $e) {
			$this->removeArchive();
			$this->removeWorkingDir();

			fatal_error($e->getMessage());
		}
	}

	private function download(string $file): void
	{
		if (! is_file($file)) {
			throw new Exception('The package archive is missing.');
		}

		ob_end_clean();

		$pretty_name = $this->snake_name . '_' . $this->skeleton['version'] . $this->getVersionSuffix() . '.zip';

		header_remove('content-encoding');
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: ' . $this->buildContentDisposition($pretty_name));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));

		if ($fd = fopen($file, 'rb')) {
			while (! feof($fd)) {
				print fread($fd, 1024);
			}

			fclose($fd);
		}

		exit;
	}

	private function makeDir(string $path): void
	{
		if (! mktree($path, 0777)) {
			throw new Exception('Cannot create the directory: ' . $path);
		}
	}

	private function writeFile(string $path, string $content, int $flags = LOCK_EX): void
	{
		if (@file_put_contents($path, $content, $flags) === false) {
			throw new Exception('Cannot write the file: ' . $path);
		}
	}

	private function copyFile(string $source, string $target): void
	{
		if (! @copy($source, $target)) {
			throw new Exception('Cannot copy the file: ' . $source);
		}
	}

	private function writePackageFile(string $path, string $content): void
	{
		if (package_put_contents($path, $content) === false) {
			// A failed write must not remain in the package cache, or the next
			// package_flush_cache() call would abort with a warning
			global $package_cache;

			unset($package_cache[$path]);

			throw new Exception('Cannot write the file: ' . $path);
		}
	}

	/**
	 * Builds the ready ZIP archive in the system temporary directory and
	 * remembers its path, so that a failure at any stage allows the leftover
	 * .tmp file to be removed.
	 */
	private function createArchive(): string
	{
		$filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(5)) . $this->getVersionSuffix();

		$this->archive = $filename . '.zip';

		$phar  = new PharData($filename . '.tmp');
		$files = $phar->buildFromDirectory($this->path);

		if (empty($files)) {
			throw new Exception('Cannot build the package archive.');
		}

		$phar->convertToData(Phar::ZIP, Phar::NONE, 'zip');

		unset($phar);

		if (! is_file($this->archive) || (int) filesize($this->archive) === 0) {
			throw new Exception('Cannot create the package archive.');
		}

		return $this->archive;
	}

	/**
	 * Removes the temporary archive files, whatever the download outcome was.
	 */
	private function removeArchive(): void
	{
		if ($this->archive === null) {
			return;
		}

		foreach ([$this->archive, str_replace('.zip', '.tmp', $this->archive)] as $file) {
			if (is_file($file)) {
				@unlink($file);
			}
		}
	}

	/**
	 * Removes the working folder of a failed build. The folder of a successful
	 * build is kept in Packages on purpose, to allow installing the mod right
	 * away without a new download.
	 */
	private function removeWorkingDir(): void
	{
		try {
			$this->assertPathWithinAllowedBase();
		} catch (InvalidArgumentException) {
			return;
		}

		deltree($this->path);
	}

	private function createReadmes(): void
	{
		if (empty($this->skeleton['make_readme']) || empty($this->skeleton['readmes']))
			return;

		$this->makeDir($this->path . '/readme');

		foreach ($this->skeleton['readmes'] as $lang => $text) {
			$this->writePackageFile(
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
		if (
			empty($this->skeleton['tables'])
			&& empty($this->skeleton['min_php_version'])
			&& empty($this->skeleton['scheduled_tasks'])
		)
			return;

		$database = "<?php\n\n";

		// The short alias of the SMF 3.0 database API keeps the modern calls readable
		if ($this->needsSmf3Code() && (! empty($this->skeleton['tables']) || ! empty($this->skeleton['scheduled_tasks']))) {
			$database .= "use SMF\\Db\\DatabaseApi as Db;\n\n";
		}

		$database .= $this->getScriptHeader('install');

		if (! empty($this->skeleton['min_php_version'])) {
			$minPhpVersion = (string) $this->skeleton['min_php_version'];

			$database .= "if (version_compare(PHP_VERSION, " . var_export($minPhpVersion, true) . ", '<')) {\n";
			$database .= "\tdie(" . var_export(
				"This mod needs PHP $minPhpVersion or greater. You will not be able to install/use this mod. Please, contact your host and ask for a php upgrade.",
				true
			) . ");\n";
			$database .= "}\n\n";
		}

		if (! empty($this->skeleton['tables'])) {
			$database .= "if (SMF === 'SSI' && ! \$user_info['is_admin']) {\n";
			$database .= "\tdie('Admin privileges required.');\n";
			$database .= "}\n\n";
		}

		foreach ($this->skeleton['tables'] as $table) {
			$database .= "\$tables[] = [\n";
			$database .= "\t'name' => " . var_export($table['name'], true) . ",\n";
			$database .= "\t'columns' => [\n";

			$table_index = false;

			foreach ($table['columns'] as $column) {
				if (! empty($column['auto'])) {
					$table_index = $column['name'];
				}

				$database .= "\t\t[\n";
				$database .= "\t\t\t'name' => " . var_export($column['name'], true) . ",\n";
				$database .= "\t\t\t'type' => " . var_export($column['type'], true) . ",\n";

				if (! in_array($column['type'], ['text', 'mediumtext'])) {
					if (! empty($column['size'])) {
						$database .= "\t\t\t'size' => " . (int) $column['size'] . ",\n";
					}

					if (empty($column['auto']) && strlen((string) $column['default'])) {
						$database .= "\t\t\t'default' => {$this->getDefaultValue($column)},\n";
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

			// The generated installer always passes the indexes array to db_create_table()
			$database .= "\t'indexes' => [\n";

			if (! empty($table_index)) {
				$database .= "\t\t[\n";
				$database .= "\t\t\t'type' => 'primary',\n";
				$database .= "\t\t\t'columns' => [" . var_export($table_index, true) . "],\n";
				$database .= "\t\t]\n";
			}

			$database .= "\t],\n";
			$database .= "];\n\n";
		}

		if (! empty($this->skeleton['tables'])) {
			$database .= "foreach (\$tables as \$table) {\n";

			if ($this->isMixedTarget()) {
				$database .= "\tif (" . self::SMF3_CHECK . ") {\n";
				$database .= "\t\tDb::\$db->create_table('{db_prefix}' . \$table['name'], \$table['columns'], \$table['indexes']);\n";
				$database .= "\t} else {\n";
				$database .= "\t\tglobal \$smcFunc;\n\n";
				$database .= "\t\t\$smcFunc['db_create_table']('{db_prefix}' . \$table['name'], \$table['columns'], \$table['indexes']);\n";
				$database .= "\t}\n";
			} elseif ($this->needsSmf3Code()) {
				$database .= "\tDb::\$db->create_table('{db_prefix}' . \$table['name'], \$table['columns'], \$table['indexes']);\n";
			} else {
				$database .= "\t\$smcFunc['db_create_table']('{db_prefix}' . \$table['name'], \$table['columns'], \$table['indexes']);\n";
			}

			$database .= "}\n\n";
		}

		$this->addScheduledTasks($database);

		$database .= "if (SMF === 'SSI') {\n";
		$database .= "\techo 'Database changes are complete!';\n";
		$database .= "}";

		$this->writePackageFile($this->path . '/database.php', $database);
	}

	/**
	 * The registration of the scheduled tasks: the rows that are already in
	 * the database are kept as is (with their schedule and enabled state), and
	 * only the missing ones are inserted, so a repeated installation never
	 * creates duplicates.
	 */
	private function addScheduledTasks(string &$database): void
	{
		if (empty($this->skeleton['scheduled_tasks'])) {
			return;
		}

		$columns = [
			"\t'next_time'       => 'int',",
			"\t'time_offset'     => 'int',",
			"\t'time_regularity' => 'int',",
			"\t'time_unit'       => 'string',",
			"\t'disabled'        => 'int',",
			"\t'task'            => 'string',",
			"\t'callable'        => 'string'",
		];

		$database .= "\$taskColumns = [\n" . implode("\n", $columns) . "\n];\n\n";

		$rows = [];

		foreach ($this->skeleton['scheduled_tasks'] as $task) {
			[$regularity, $unit] = $this->getScheduledTaskRegularities($task);

			$rows[] = "\t[\n"
				. "\t\t'next_time'       => strtotime('tomorrow'),\n"
				. "\t\t'time_offset'     => 0,\n"
				. "\t\t'time_regularity' => $regularity,\n"
				. "\t\t'time_unit'       => " . var_export($unit, true) . ",\n"
				. "\t\t'disabled'        => 0,\n"
				. "\t\t'task'            => " . var_export($task['slug'], true) . ",\n"
				. "\t\t'callable'        => " . var_export($task['callable'] ?? '', true) . "\n"
				. "\t]";
		}

		$database .= "\$taskRows = [\n" . implode(",\n", $rows) . "\n];\n\n";

		$database .= "// Repeated installation must not create duplicate tasks\n";
		$database .= "\$existingTasks = [];\n\n";

		$query = "\n"
			. "\tSELECT task\n"
			. "\tFROM {db_prefix}scheduled_tasks\n"
			. "\tWHERE task IN ({array_string:tasks})',\n"
			. "\t[\n"
			. "\t\t'tasks' => array_column(\$taskRows, 'task'),\n"
			. "\t]\n"
			. ")";

		$legacyCheck = "\$request = \$smcFunc['db_query']('', '" . $query
			. ";\n\nwhile (\$row = \$smcFunc['db_fetch_assoc'](\$request)) {"
			. "\n\t\$existingTasks[] = \$row['task'];"
			. "\n}\n\n"
			. "\$smcFunc['db_free_result'](\$request);";

		$modernCheck = "\$request = Db::\$db->query('" . $query
			. ";\n\nwhile (\$row = Db::\$db->fetch_assoc(\$request)) {"
			. "\n\t\$existingTasks[] = \$row['task'];"
			. "\n}\n\n"
			. "Db::\$db->free_result(\$request);";

		if ($this->isMixedTarget()) {
			$database .= "if (" . self::SMF3_CHECK . ") {\n";
			$database .= $this->indentLines($modernCheck) . "\n";
			$database .= "} else {\n";
			$database .= "\tglobal \$smcFunc;\n\n";
			$database .= $this->indentLines($legacyCheck) . "\n";
			$database .= "}\n\n";
		} elseif ($this->needsSmf3Code()) {
			$database .= $modernCheck . "\n\n";
		} else {
			$database .= $legacyCheck . "\n\n";
		}

		$database .= "foreach (\$taskRows as \$key => \$taskRow) {\n";
		$database .= "\tif (in_array(\$taskRow['task'], \$existingTasks)) {\n";
		$database .= "\t\tunset(\$taskRows[\$key]);\n";
		$database .= "\t}\n";
		$database .= "}\n\n";

		$database .= "if (\$taskRows) {\n";

		if ($this->isMixedTarget()) {
			$database .= "\tif (" . self::SMF3_CHECK . ") {\n";
			$database .= "\t\tDb::\$db->insert('', '{db_prefix}scheduled_tasks', \$taskColumns, \$taskRows, ['id_task']);\n";
			$database .= "\t} else {\n";
			$database .= "\t\t\$smcFunc['db_insert']('', '{db_prefix}scheduled_tasks', \$taskColumns, \$taskRows, ['id_task']);\n";
			$database .= "\t}\n";
		} elseif ($this->needsSmf3Code()) {
			$database .= "\tDb::\$db->insert('', '{db_prefix}scheduled_tasks', \$taskColumns, \$taskRows, ['id_task']);\n";
		} else {
			$database .= "\t\$smcFunc['db_insert']('', '{db_prefix}scheduled_tasks', \$taskColumns, \$taskRows, ['id_task']);\n";
		}

		$database .= "}\n\n";
	}

	/**
	 * @return array{0: int, 1: string} The regularity value and the time unit
	 */
	private function getScheduledTaskRegularities(array $task): array
	{
		return match ((int) $task['regularity']) {
			0       => [1, 'd'],
			1       => [1, 'w'],
			default => [4, 'w'],
		};
	}

	/**
	 * The uninstall helpers: the plain script removes the task registrations
	 * of this mod on every uninstall, and the database script removes the
	 * settings and tables — the latter is executed by the package manager only
	 * when the user opts into the data removal during the uninstall.
	 */
	private function createUninstallScripts(): void
	{
		$hasTasks = ! empty($this->skeleton['scheduled_tasks']) || ! empty($this->skeleton['background_tasks']);
		$hasData  = ! empty($this->skeleton['options']) || ! empty($this->skeleton['tables']);

		if (! $hasTasks && ! $hasData) {
			return;
		}

		$prefix = "<?php\n\n";

		// The short alias of the SMF 3.0 database API keeps the modern calls readable
		if ($this->needsSmf3Code()) {
			$prefix .= "use SMF\\Db\\DatabaseApi as Db;\n\n";
		}

		$prefix .= $this->getScriptHeader('uninstall');

		if ($hasTasks) {
			$this->writePackageFile($this->path . '/uninstall.php', $prefix . $this->getUninstallTasksScript());
		}

		if ($hasData) {
			$script = $prefix;

			$script .= "if (SMF === 'SSI' && ! \$user_info['is_admin']) {\n";
			$script .= "\tdie('Admin privileges required.');\n";
			$script .= "}\n\n";

			$this->writePackageFile($this->path . '/uninstall-database.php', $script . $this->getUninstallDataScript());
		}
	}

	/**
	 * After the uninstall the mod's classes are gone, so its scheduled tasks
	 * and queued background tasks must not stay active: both would fail on
	 * every run while the mod is not installed.
	 */
	private function getUninstallTasksScript(): string
	{
		$legacyLines = [];
		$modernLines = [];

		if (! empty($this->skeleton['scheduled_tasks'])) {
			$taskSlugs = array_map(
				fn (array $task): string => (string) $task['slug'],
				$this->skeleton['scheduled_tasks']
			);

			$query  = "\n\tDELETE FROM {db_prefix}scheduled_tasks\n\tWHERE task IN ({array_string:tasks})";
			$params = "\t[\n\t\t'tasks' => " . $this->getExportedValues($taskSlugs) . "\n\t]";

			$legacyLines[] = "\$smcFunc['db_query']('', '" . $query . "',\n" . $params . "\n);";
			$modernLines[] = "Db::\$db->query('" . $query . "',\n" . $params . "\n);";
		}

		if (! empty($this->skeleton['background_tasks'])) {
			$taskFiles = array_map(
				fn (array $task): string => '$sourcedir/' . $this->classname . '/Tasks/' . $task['classname'] . '.php',
				$this->skeleton['background_tasks']
			);

			$query  = "\n\tDELETE FROM {db_prefix}background_tasks\n\tWHERE task_file IN ({array_string:files})";
			$params = "\t[\n\t\t'files' => " . $this->getExportedValues($taskFiles) . "\n\t]";

			$legacyLines[] = "\$smcFunc['db_query']('', '" . $query . "',\n" . $params . "\n);";
			$modernLines[] = "Db::\$db->query('" . $query . "',\n" . $params . "\n);";
		}

		return $this->attachVersionLines($legacyLines, $modernLines);
	}

	/**
	 * The settings and tables of this mod are the user data: they are removed
	 * only when the uninstall database script is actually executed, i.e. when
	 * the user has chosen to remove all the data along with the mod.
	 */
	private function getUninstallDataScript(): string
	{
		$legacyLines = [];
		$modernLines = [];

		if (! empty($this->skeleton['options'])) {
			$settingKeys = array_map(
				fn (array $option): string => $this->snake_name . '_' . $option['name'],
				$this->skeleton['options']
			);

			$query  = "\n\tDELETE FROM {db_prefix}settings\n\tWHERE variable IN ({array_string:settings})";
			$params = "\t[\n\t\t'settings' => " . $this->getExportedValues($settingKeys) . "\n\t]";

			$legacyLines[] = "\$smcFunc['db_query']('', '" . $query . "',\n" . $params . "\n);";
			$modernLines[] = "Db::\$db->query('" . $query . "',\n" . $params . "\n);";
		}

		foreach ($this->skeleton['tables'] as $table) {
			$legacyLines[] = "\$smcFunc['db_drop_table']('{db_prefix}" . $table['name'] . "');";
			$modernLines[] = "Db::\$db->drop_table('{db_prefix}" . $table['name'] . "');";
		}

		return $this->attachVersionLines($legacyLines, $modernLines);
	}

	/**
	 * Emits the given statements for the selected SMF version: separately for a
	 * single target version, or wrapped into a runtime version check for the
	 * mixed one.
	 *
	 * @param list<string> $legacyLines
	 * @param list<string> $modernLines
	 */
	private function attachVersionLines(array $legacyLines, array $modernLines): string
	{
		if (! $this->isMixedTarget()) {
			$lines = $this->needsSmf3Code() ? $modernLines : $legacyLines;

			return $lines === [] ? '' : implode("\n\n", $lines) . "\n\n";
		}

		if ($legacyLines === [] && $modernLines === []) {
			return '';
		}

		$script = "if (" . self::SMF3_CHECK . ") {\n";

		foreach ($modernLines as $line) {
			$script .= $this->indentLines($line) . "\n";
		}

		$script .= "} else {\n";

		if ($legacyLines !== []) {
			$script .= "\tglobal \$smcFunc;\n\n";

			foreach ($legacyLines as $line) {
				$script .= $this->indentLines($line) . "\n";
			}
		}

		$script .= "}\n\n";

		return $script;
	}

	/**
	 * @param list<string|int|float|bool> $values
	 */
	private function getExportedValues(array $values): string
	{
		return '[' . implode(', ', array_map(
			static fn (string|int|float|bool $value): string => var_export($value, true),
			$values
		)) . ']';
	}

	/**
	 * The shared bootstrap of the generated scripts: standalone runs via SSI
	 * are allowed, everything else requires the SMF context.
	 */
	private function getScriptHeader(string $action): string
	{
		$header  = "if (file_exists(dirname(__FILE__) . '/SSI.php') && ! defined('SMF')) {\n";
		$header .= "\trequire_once(dirname(__FILE__) . '/SSI.php');\n";
		$header .= "} elseif (! defined('SMF')) {\n";
		$header .= "\tdie('<b>Error:</b> Cannot $action - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');\n";
		$header .= "}\n\n";

		return $header;
	}

	/**
	 * Shifts every non-empty line of the given code one level deeper,
	 * including the lines of embedded multi-line statements.
	 */
	private function indentLines(string $code): string
	{
		return preg_replace_callback('/^[^\n]/m', static fn (array $match): string => "\t" . $match[0], $code);
	}

	private function getDefaultValue(array $column): string
	{
		$value = match ($column['type']) {
			'tinyint',
			'int',
			'mediumint' => (int) $column['default'],
			default     => $column['default'],
		};

		return var_export($value, true);
	}

	/**
	 * The generated code references the language keys only when the mod has
	 * settings, filled titles/descriptions, options or scheduled tasks, so a
	 * basic mod must not request any language files at all.
	 */
	private function needsLangFiles(): bool
	{
		return ! empty($this->skeleton['title'])
			|| ! empty($this->skeleton['description'])
			|| ! empty($this->skeleton['options'])
			|| ! empty($this->skeleton['scheduled_tasks'])
			|| ! empty($this->skeleton['settings_area']);
	}

	private function createLangs(): void
	{
		global $txt;

		if (! $this->needsLangFiles()) {
			return;
		}

		$languages = $this->skeleton['languages'];

		$entries = [];

		// The generated code references the base keys unconditionally, so every
		// language file must contain them, whatever translations are filled in
		foreach ($languages as $lang) {
			$title = ($this->skeleton['title'][$lang] ?? '') ?: $this->skeleton['name'];

			$entries[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_title'] = "
				. var_export($title, true) . ';';
			$entries[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_description'] = "
				. var_export($this->skeleton['description'][$lang] ?? '', true) . ';';

			if ($this->skeleton['settings_area'] === 3) {
				loadLanguage('SimpleModMaker/', $lang);

				$entries[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_section1_title'] = "
					. var_export(sprintf($txt['smm_tab_example'], 1), true) . ';';
				$entries[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_section2_title'] = "
					. var_export(sprintf($txt['smm_tab_example'], 2), true) . ';';
			}
		}

		foreach ($this->skeleton['options'] as $option) {
			foreach ($languages as $lang) {
				$value = $option['translations'][$lang]
					?? ($option['translations']['english'] ?? $option['name']);

				$entries[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_{$option['name']}'] = " . var_export($value, true) . ';';

				if (in_array($option['type'], ['select-multiple', 'select'])) {
					if (! empty($option['variants'])) {
						$variants = array_map(
							fn(string $variant): string => var_export($variant, true),
							array_map(strval(...), explode('|', $option['variants']))
						);

						$entries[$lang][] = PHP_EOL . "\$txt['{$this->snake_name}_{$option['name']}_set'] = ["
							. implode(', ', $variants) . '];';
					}
				}
			}
		}

		foreach ($this->skeleton['scheduled_tasks'] as $task) {
			foreach ($languages as $lang) {
				$name = $task['names'][$lang] ?? ($task['names']['english'] ?? $task['slug']);
				$desc = $task['descriptions'][$lang] ?? ($task['descriptions']['english'] ?? '');

				$entries[$lang][] = PHP_EOL . "\$txt['scheduled_task_{$task['slug']}'] = " . var_export($name, true) . ';';
				$entries[$lang][] = PHP_EOL . "\$txt['scheduled_task_desc_{$task['slug']}'] = " . var_export($desc, true) . ';';
			}
		}

		$this->created_languages = array_keys($entries);

		$lang_dir = $this->path . '/Themes/default/languages/' . $this->classname . (
			$this->skeleton['use_lang_dir'] ? '/.' : '.'
		);

		foreach ($entries as $lang => $data) {
			foreach ($data as $content) {
				if (! is_file($lang_file = $lang_dir . $lang . '.php')) {
					$header = "<?php\n\n";
					$header .= "/**\n";
					$header .= " * @package " . Serializer::phpComment($this->skeleton['name']) . "\n";
					$header .= " */\n";
				$content = $header . $content;
			}

			$this->writeFile($lang_file, $content, FILE_APPEND | LOCK_EX);
		}
	}
	}

	private function preparePackageInfo(): void
	{
		try {
			$imp = new DOMImplementation();
			$dtd = $imp->createDocumentType(
				'package-info', '', 'http://www.simplemachines.org/xml/package-info'
			);

			$xml = $imp->createDocument('', '', $dtd);
			$xml->appendChild($xml->createComment(' Generated by ' . SMM_NAME . ' '));

			$root = $xml->createElementNS(
				'http://www.simplemachines.org/xml/package-info', 'package-info'
			);

			$root->setAttributeNS(
				'http://www.w3.org/2000/xmlns/', 'xmlns:smf', 'http://www.simplemachines.org/'
			);

			$xml->appendChild($root);
			$xml->preserveWhiteSpace = true;
			$xml->formatOutput = true;

			$data = $this->getProperData();

			foreach ($data as $key => $value) {
				if (is_array($value)) {
					$root->appendChild($xml->createElement('empty'));

					$element = $root->appendChild($xml->createElement($this->sanitizeXmlName((string) $key)));

					foreach ($value as $k => $v) {
						if (is_array($v)) {
							if ($k === '@attributes') {
								foreach ($v as $i => $j) {
									($element->appendChild($xml->createAttribute($this->sanitizeXmlName((string) $i))))
										->appendChild($xml->createTextNode((string) $j));
								}
							} else {
								foreach ($v as $r => $s) {
									$e = $element->appendChild(
										$xml->createElement($this->sanitizeXmlName((string) $k))
									);

									if (is_string($r)) {
										$e->appendChild($xml->createTextNode($r));
									}

									foreach ((array) $s as $x => $y) {
										($e->appendChild($xml->createAttribute($this->sanitizeXmlName((string) $x))))
											->appendChild($xml->createTextNode((string) $y));
									}
								}
							}
						} else {
							$element->appendChild($xml->createElement($this->sanitizeXmlName((string) $k)))
								->appendChild($xml->createTextNode((string) $v));
						}
					}
				} else {
					$root->appendChild($xml->createElement($this->sanitizeXmlName((string) $key)))
						->appendChild($xml->createTextNode((string) $value));
				}
			}

			$xml_string = $xml->saveXML();

			// Make empty string
			$xml_string = str_replace('  <empty/>', '', $xml_string);

			// Replace spaces with tabs
			$xml_string = preg_replace_callback('/^(?: {2})+/m', function ($m) {
				$spaces = strlen($m[0]);
				$tabs   = $spaces / 2;

				return str_repeat("\t", $tabs);
			}, $xml_string);

			$this->writePackageFile($this->path . '/package-info.xml', rtrim($xml_string, PHP_EOL));
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
		$coreclass = $this->skeleton['namespace'] . '\\' . $this->classname . (empty($this->skeleton['make_dir']) ? '' : '\Integration');
		$readmes   = array_keys($this->skeleton['readmes'] ?? []);

		$data['install'] = [
			'@attributes' => [
				'for' => $this->getTargetVersion(),
			],
			'hook' => [
				[
					'hook'     => Hook::PRE_LOAD,
					'function' => $coreclass . '::hooks#',
					'file'     => '$sourcedir/' . $filename . '.php',
				],
			],
		];

		$data['uninstall'] = [
			'@attributes' => [
				'for' => $this->getTargetVersion(),
			],
		];

		// The uninstall scripts run before the hook is removed and the files
		// are deleted: the plain script cleans the task registrations, and the
		// database one removes the user data only on request
		if (is_file($this->path . '/uninstall.php'))
			$data['uninstall']['code'] = 'uninstall.php';

		if (is_file($this->path . '/uninstall-database.php'))
			$data['uninstall']['database'] = 'uninstall-database.php';

		$data['uninstall']['hook'] = [
			array_merge((array) $data['install']['hook'][0], [
				'reverse' => 'true',
			]),
		];

		if (is_file($this->path . '/database.php'))
			$data['install']['database'] = 'database.php';

		if ($this->skeleton['make_readme']) {
			foreach ($readmes as $lang) {
				$readmeData = [
					'parsebbc' => 'true'
				];

				if ($lang !== 'english') {
					$readmeData['lang'] = $lang;
				}

				$data['install']['readme']["readme/$lang.txt"] = $readmeData;
			}
		}

		if (empty($this->skeleton['make_dir'])) {
			$data['install']['require-file']['Adding main source file'] = [
				'name'        => 'Sources/' . $this->skeleton['filename'] . '.php',
				'destination' => '$sourcedir',
			];

			$data['uninstall']['remove-file']['Removing main source file'] = [
				'name' => '$sourcedir/' . $filename . '.php',
			];
		} else {
			$data['install']['require-dir']['Adding main source files'] = [
				'name'        => 'Sources/' . $this->classname,
				'destination' => '$sourcedir',
			];

			$data['uninstall']['remove-dir']['Removing main source files'] = [
				'name' => '$sourcedir/' . $this->classname,
			];
		}

		if (TemplateUtil::needsTemplate($this->skeleton)) {
			$data['install']['require-file']['Adding main template file'] = [
				'name'        => 'Themes/default/' . $this->classname . '.template.php',
				'destination' => '$themedir',
			];

			$data['uninstall']['remove-file']['Removing main template file'] = [
				'name' => '$themedir/' . $this->classname . '.template.php',
			];
		}

		if (! empty($this->skeleton['make_script'])) {
			$data['install']['require-file']['Adding JS file'] = [
				'name'        => 'Themes/default/scripts/' . $this->snake_name . '.js',
				'destination' => '$themedir/scripts',
			];

			$data['uninstall']['remove-file']['Removing JS file'] = [
				'name' => '$themedir/scripts/' . $this->snake_name . '.js',
			];
		}

		if (! empty($this->skeleton['make_css'])) {
			$data['install']['require-file']['Adding CSS file'] = [
				'name'        => 'Themes/default/css/' . $this->snake_name . '.css',
				'destination' => '$themedir/css',
			];

			$data['uninstall']['remove-file']['Removing CSS file'] = [
				'name' => '$themedir/css/' . $this->snake_name . '.css',
			];
		}

		if (empty($this->skeleton['use_lang_dir'])) {
			foreach ($this->created_languages as $lang) {
				$language_file     = 'Themes/default/languages/' . $this->classname . '.' . $lang . '.php';
				$language_filepath = $this->path . '/' . $language_file;

				if (is_file($language_filepath)) {
					$data['install']['require-file']['Adding ' . ucfirst($lang) . ' language file'] = [
						'name'        => $language_file,
						'destination' => rtrim($this->getLanguagesPath(), '/'),
					];

					$data['uninstall']['remove-file']['Removing ' . ucfirst($lang) . ' language file'] = [
						'name' => $this->getLanguagesPath() . $this->classname . '.' . $lang . '.php',
					];
				}
			}
		} else {
			$data['install']['require-dir']['Adding language files'] = [
				'name'        => 'Themes/default/languages/' . $this->classname,
				'destination' => rtrim($this->getLanguagesPath(), '/'),
			];

			$data['uninstall']['remove-dir']['Removing language files'] = [
				'name' => $this->getLanguagesPath() . $this->classname,
			];
		}

		if (! empty($this->skeleton['options'])) {
			$data['install']['redirect'][] = [
				'url'     => $this->getSettingsRedirectUrl(),
				'timeout' => '3000',
			];
		}

		return $data;
	}

	/**
	 * After installation, the user is taken to the page with the mod settings:
	 * the general section of Modification Settings for area 1, the mod's own
	 * subsection or admin area for areas 2 and 3.
	 */
	private function getSettingsRedirectUrl(): string
	{
		return match ((int) $this->skeleton['settings_area']) {
			1       => '?action=admin;area=modsettings;sa=general',
			3       => '?action=admin;area=' . $this->snake_name,
			default => '?action=admin;area=modsettings;sa=' . $this->snake_name,
		};
	}

	/**
	 * The archive suffix reflects the SMF versions the package is built for.
	 */
	private function getVersionSuffix(): string
	{
		return match ($this->skeleton['smf_target_version']) {
			'3.0'     => '_smf30',
			'2.1/3.0' => '_smf21_smf30',
			default   => '_smf21',
		};
	}

	/**
	 * Whether the generated code must use the modern API of SMF 3.0.
	 */
	private function needsSmf3Code(): bool
	{
		return in_array($this->skeleton['smf_target_version'], ['3.0', '2.1/3.0'], true);
	}

	private function isMixedTarget(): bool
	{
		return $this->skeleton['smf_target_version'] === '2.1/3.0';
	}

	private function getLanguagesPath(): string
	{
		return $this->skeleton['smf_target_version'] === '2.1' ? '$languagedir/' : '$themedir/languages/';
	}

	private function getTargetVersion(): string
	{
		return match ($this->skeleton['smf_target_version']) {
			'2.1/3.0' => '2.1.*, 3.0.*',
			default   => $this->skeleton['smf_target_version'] . '.*',
		};
	}

	/**
	 * Builds a safe Content-Disposition header. The ASCII fallback strips any
	 * character that could break the quoted-string or inject headers, while the
	 * RFC 5987 parameter preserves the original (possibly Unicode) name.
	 */
	private function buildContentDisposition(string $filename): string
	{
		$ascii = preg_replace('/[^A-Za-z0-9._\-]/', '_', $filename) ?? 'download.zip';

		return "attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($filename);
	}

	/**
	 * Reduces an arbitrary label to a valid XML element/attribute name so that
	 * user data can never be interpreted as markup. Values are always emitted
	 * through createTextNode(), which escapes them for the XML context.
	 */
	private function sanitizeXmlName(string $name): string
	{
		$name = preg_replace('/[^A-Za-z0-9_\-]/', '-', $name) ?? '';

		if ($name === '' || preg_match('/^[A-Za-z_]/', $name) !== 1) {
			$name = 'n' . $name;
		}

		return $name;
	}

	/**
	 * @param array<string, mixed> $options
	 * @return list<string>
	 */
	private function resolveAllowedBases(array $options): array
	{
		$bases = [];

		foreach (['allowed_bases', 'allowed_base', 'base', 'package_base', 'build_base'] as $key) {
			if (! isset($options[$key])) {
				continue;
			}

			if (is_array($options[$key])) {
				$bases = array_merge($bases, $options[$key]);
			} else {
				$bases[] = (string) $options[$key];
			}
		}

		if ($bases === []) {
			global $packagesdir;

			if (isset($packagesdir) && is_string($packagesdir) && $packagesdir !== '') {
				$bases[] = $packagesdir;
			}

			$bases[] = sys_get_temp_dir();
			$bases[] = (string) (getcwd() ?: sys_get_temp_dir());
		}

		$resolved = [];

		foreach ($bases as $base) {
			if (! is_string($base) || trim($base) === '') {
				continue;
			}

			$real = realpath($base);

			$resolved[] = rtrim($real !== false ? $real : $base, "\\/");
		}

		return array_values(array_unique($resolved));
	}

	private function assertPathWithinAllowedBase(): void
	{
		$path = $this->path;

		if (! is_string($path) || trim($path) === '') {
			throw new InvalidArgumentException('Build path is invalid.');
		}

		$resolved = realpath($path);

		if ($resolved === false) {
			$parent = realpath(dirname($path));

			if ($parent === false) {
				throw new InvalidArgumentException('Build path is outside the allowed base directory.');
			}

			$resolved = rtrim($parent, "\\/") . DIRECTORY_SEPARATOR . basename($path);
		} else {
			$resolved = rtrim($resolved, "\\/");
		}

		$baseName = basename($resolved);

		if ($baseName === '' || $baseName === '.' || $baseName === '..') {
			throw new InvalidArgumentException('Build path is invalid.');
		}

		foreach ($this->allowed_bases as $base) {
			$base = rtrim($base, "\\/");

			if ($base === '') {
				continue;
			}

			if (strcasecmp($resolved, $base) === 0) {
				throw new InvalidArgumentException('Build path cannot be the allowed base directory itself.');
			}

			$prefix = $base . DIRECTORY_SEPARATOR;

			if (strncasecmp($resolved, $prefix, strlen($prefix)) === 0) {
				return;
			}
		}

		throw new InvalidArgumentException('Build path is outside the allowed base directory.');
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
