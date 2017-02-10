<?php
/**
*
* @package Auto db Backup (3.2)
* @copyright (c) 2015 david63
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace david63\autodbbackup\migrations;

use phpbb\db\migration\migration;

class version_2_1_0 extends migration
{
	public function update_data()
	{
		return array(
			array('config.add', array('auto_db_backup_copies', 5)),
			array('config.add', array('auto_db_backup_enable', 0)),
			array('config.add', array('auto_db_backup_filetype', 'text')),
			array('config.add', array('auto_db_backup_gc', 3600)),
			array('config.add', array('auto_db_backup_last_gc', time())),
			array('config.add', array('auto_db_backup_optimize', 0)),
			array('config.add', array('auto_db_backup_timezone', '')),

			// Add the ACP module
			array('module.add', array('acp', 'ACP_CAT_MAINTENANCE', 'ACP_AUTO_DB_BACKUP')),

			array('module.add', array(
				'acp', 'ACP_AUTO_DB_BACKUP', array(
					'module_basename'	=> '\david63\autodbbackup\acp\auto_db_backup_module',
					'modes'				=> array('main'),
				),
			)),
		);
	}
}
