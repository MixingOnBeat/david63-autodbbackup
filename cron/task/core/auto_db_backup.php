<?php
/**
*
* @package Auto db Backup (3.2)
* @copyright (c) 2015 david63
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace david63\autodbbackup\cron\task\core;

use Symfony\Component\DependencyInjection\ContainerInterface;

class auto_db_backup extends \phpbb\cron\task\base
{
	/** @var string phpBB root path */
	protected $root_path;

	/** @var string phpBB extension */
	protected $php_ext;

	/** @var string phpBB table prefix */
	protected $phpbb_table_prefix;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var \phpbb\user */
	protected $user;

	/** @var ContainerInterface */
	protected $container;

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

	/**
	* Constructor for cron auto_db_backup
	*
	* @param string								$root_path
	* @param string								$php_ext
	* @param string								$phpbb_table_prefix
	* @param \phpbb\config\config				$config					Config object
	* @param \phpbb\db\driver\driver_interface	$db
	* @param \phpbb\log\log						$log					phpBB log
	* @param \phpbb\user						$user					User object
	* @param ContainerInterface					$container				Service container interface
	*
	* @return \david63\autodbbackup\cron\task\core\auto_db_backup
	* @access public
	*/
	public function __construct($phpbb_root_path, $php_ext, $phpbb_table_prefix, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\log\log $log, \phpbb\user $user, ContainerInterface $phpbb_container, \phpbb\event\dispatcher_interface $dispatcher)
	{
		$this->phpbb_root_path	= $phpbb_root_path;
		$this->php_ext			= $php_ext;
		$this->table_prefix		= $phpbb_table_prefix;
		$this->config			= $config;
		$this->db  				= $db;
		$this->log				= $log;
		$this->user				= $user;
		$this->container		= $phpbb_container;
		$this->dispatcher		= $dispatcher;
	}

	/**
	* Runs this cron task.
	*
	* @return null
	*/
	public function run()
	{
		// Need to include this file for the get_usable_memory() function
		if (!function_exists('get_usable_memory'))
		{
			include_once($this->phpbb_root_path . 'includes/acp/acp_database.' . $this->php_ext);
		}

		@set_time_limit(1200);
		@set_time_limit(0);

		$this->db_tools = $this->container->get('dbal.tools');
		$tables			= $this->db_tools->sql_list_tables();
		$time			= time();
		$filename		= 'backup_' . $time . '_' . unique_id();
		$file_type		= $this->config['auto_db_backup_filetype'];
		$location		= $this->phpbb_root_path . '/store/';
		$extractor		= $this->container->get('dbal.extractor');
		$extension 		= $this->get_extension($file_type);

		$extractor->init_extractor($file_type, $filename, $time, false, true);
		$extractor->write_start($this->table_prefix);

		foreach ($tables as $table_name)
		{
			$extractor->write_table($table_name);

			if ($this->config['auto_db_backup_optimize'])
			{
				switch ($this->db->get_sql_layer())
				{
					case 'sqlite':
					case 'sqlite3':
						$extractor->flush('DELETE FROM ' . $table_name . ";\n");
					break;

					case 'mssql':
					case 'mssql_odbc':
					case 'mssqlnative':
						$extractor->flush('TRUNCATE TABLE ' . $table_name . "GO\n");
					break;

					case 'oracle':
						$extractor->flush('TRUNCATE TABLE ' . $table_name . "/\n");
					break;

					default:
						$extractor->flush('TRUNCATE TABLE ' . $table_name . ";\n");
					break;
				}
			}
			$extractor->write_data($table_name);
		}
		$extractor->write_end();

		/**
		* Event to allow exporting of a backup file
		*
		* @event david63.autodbbackup.backup_file_export
		* @var	string	filename	The backup filename
		* @var	string	file_type	The backup file type (text, bzip2 or gzip)
		* @var	string	extension	The file extension for the file type
		* @var	string	location	The location of the backup files
		*
		* @since 2.1.0
		*/
		$vars = array(
			'filename',
			'file_type',
			'extension',
			'location',
		);
		extract($this->dispatcher->trigger_event('david63.autodbbackup.backup_file_export', compact($vars)));

		// Delete backup copies
		if ($this->config['auto_db_backup_copies'] > 0)
		{
			$dir	= opendir($location);
			$files	= array();

			while (($file = readdir($dir)) !== false)
			{
				if (is_file($location . $file) && (substr($file, -3) == '.gz' || substr($file, -4) == '.bz2' || substr($file, -4) == '.sql' ))
				{
					$files[$file] = fileatime($location . $file);
				}
			}
			closedir($dir);

			arsort($files);
			reset($files);

			if (sizeof($files) > $this->config['auto_db_backup_copies'])
			{
				$i = 0;
				while (list($key, $val) = each($files))
				{
					$i++;
					if ($i > $this->config['auto_db_backup_copies'])
					{
						@unlink($location . $key);
					}
				}
			}
		}

		// Update the last backup time and write the log entry
		$this->config->set('auto_db_backup_last_gc', time(), true);
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AUTO_DB_BACKUP');
	}

	/**
	* Get the extension type.
	*
	* @return $extension
	*/
	protected function get_extension($file_type)
	{
		switch ($file_type)
		{
			case 'gzip':
				$extension = '.sql.gz';
			break;

			case 'bzip2':
				$extension = '.sql.bz2';
			break;

			default:
				$extension = '.sql';
			break;
		}

		return $extension;
	}

	/**
	* Returns whether this cron task can run, given current board configuration.
	*
	* @return bool
	*/
	public function is_runnable()
	{
		return (bool) $this->config['auto_db_backup_enable'];
	}

	/**
	* Returns whether this cron task should run now, because enough time
	* has passed since it was last run.
	*
	* @return bool
	*/
	public function should_run()
	{
		return $this->config['auto_db_backup_last_gc'] < time() - $this->config['auto_db_backup_gc'];
	}
}
