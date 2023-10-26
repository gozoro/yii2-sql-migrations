<?php

namespace gozoro\sql_migrations;


use Yii;
use yii\console\Exception;
use yii\helpers\Console;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;




/**
 * BaseMigrateController is base class for migrate controllers.
 */
abstract class MigrateBaseController extends \yii\console\Controller
{
	/**
     * @var string the default command action.
     */
    public $defaultAction = 'up';

    /**
     * @var string the directory storing the migration files. This can be either
     * a path alias or a directory.
     */
    public $migrationPath = '@app/migrations';

    /**
     * @var string the name of the table for keeping applied migration information.
     */
    public $migrationTable = '{{%migration}}';


    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection to use
     * when applying migrations. Starting from version 2.0.3, this can also be a configuration array
     * for creating the object.
     */
    public $db = 'db';




	/**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            ['migrationPath', 'migrationTable', 'db'] // global for all actions
        );
    }


    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param \yii\base\Action $action the action to be executed.
     * @throws Exception if directory specified in migrationPath doesn't exist and action isn't "create".
     * @return boolean whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action))
		{
            $path = Yii::getAlias($this->migrationPath);
            if (!is_dir($path))
			{
                throw new Exception("Migration failed. Directory specified in migrationPath doesn't exist: {$this->migrationPath}");
            }
            $this->migrationPath = $path;
			$this->db = Instance::ensure($this->db, Connection::class);

            $this->stdout("SQL Migration Tool\n\n");

            return true;
        }
		else
		{
            return false;
        }
    }


    /**
     * Creates the migration history table.
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->schema->getRawTableName($this->migrationTable);
        $this->stdout("Creating migration history table \"$tableName\"...", Console::FG_YELLOW);
        $this->db->createCommand()->createTable($this->migrationTable, [
			'version' => 'integer NOT NULL PRIMARY KEY',
            'name' => 'varchar(180) NOT NULL',
            'created_at' => 'datetime',
        ])->execute();
        $this->stdout("Done.\n", Console::FG_GREEN);
    }


    /**
     * Returns the migration history.
     * @param integer $limit the maximum number of records in the history to be returned. `null` for "no limit".
     * @return array the migration history
     */
    protected function getMigrationHistory($limit = null)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null)
		{
            $this->createMigrationHistoryTable();
        }

        $query = new Query;
        $rows = $query->select(['version', 'name', 'created_at'])
            ->from($this->migrationTable)
            ->orderBy('created_at DESC, version DESC')
            ->limit($limit)
            ->createCommand($this->db)
            ->queryAll();

        return $rows;
    }


	/**
	 * Adds new migration entry to the history.
	 * @param string $migrationName migration name.
	 */
	protected function addMigrationHistory($migrationName)
	{
		$command = $this->db->createCommand();
		return $command->insert($this->migrationTable, [
			'version' => (int)$migrationName,
			'name' => $migrationName,
			'created_at' => date('Y-m-d H:i:s'),
		])->execute();
	}

	/**
	 * Removes existing migration from the history.
	 * @param string $migrationName migration name.
	 */
	protected function removeMigrationHistory($migrationName)
	{
		$command = $this->db->createCommand();
		return $command->delete($this->migrationTable, [
			'name' => $migrationName,
		])->execute();
	}


	/**
     * Executes a SQL statement.
     * This method executes the specified SQL statement using [[db]].
     * @param string $sql the SQL statement to be executed
     * @return bool whether the sql execution is successful
     */
    protected function executeSql($sql)
    {
		try
		{
			$command = $this->db->createCommand($sql);
			$command->execute();

			// it's a hack for throwing an exception from fail SQL statement
			while ($command->pdoStatement->nextRowSet()){}

			return true;
		}
		catch (\Exception $e)
		{
			$this->stdout("Execute SQL error::" . $e->getMessage() . "\n\n" , Console::FG_RED);
			return false;
		}
    }


	/**
	 * Returns migration files that are not applied.
	 * @return array list of new migration files
	 */
	protected function getNewMigrationFiles()
	{
		$applied = [];
		foreach ($this->getMigrationHistory(null) as $historyRow)
		{
			$applied[$historyRow['version']] = true;
		}

		$newMigrationFiles = [];
		$handle = opendir($this->migrationPath);
		while (($file = readdir($handle)) !== false)
		{
			if ($file === '.' or $file === '..')
			{
				continue;
			}

			$path = $this->migrationPath . DIRECTORY_SEPARATOR . $file;
			$version = (int)$file;

			if(\is_file($path) and \preg_match('/\.up\.sql$/', $file) and !isset($applied[$version]))
			{
				$newMigrationFiles[] = $file;
			}


		}
		closedir($handle);
		sort($newMigrationFiles);

		return $newMigrationFiles;
	}


    /**
     * Upgrades with the specified migration UP-file.
     * @param string $upFile the migration up.sql-file
     * @return boolean whether the migration is successful
     */
    protected function migrateUp($upFile)
    {
		$this->stdout("Migration UP: $upFile\n", Console::FG_YELLOW);

		$filename = $this->migrationPath . DIRECTORY_SEPARATOR . $upFile;

		if(!\file_exists($filename))
		{
			$this->stdout("*** No such file: $filename\n\n", Console::FG_RED);
			return false;
		}

		$sql = \file_get_contents($filename);
		$start = microtime(true);

		if($this->executeSql($sql))
		{
			$this->addMigrationHistory($upFile);
			$time = microtime(true) - $start;
			$this->stdout("*** successed execute $upFile (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
			return true;
		}
		else
		{
			$time = microtime(true) - $start;
			$this->stdout("*** failed execute $upFile (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
			return false;
		}
    }



    /**
     * Downgrade from a specified migration UP-file.
     * @param string $upFile the migration up.sql-file!
     * @return boolean whether the migration is successful
     */
    protected function migrateDown($upFile)
    {
        $downFile = preg_replace('/\.up\.sql$/', '.down.sql', $upFile);

		$this->stdout("Migration DOWN: $downFile\n", Console::FG_YELLOW);

		$filename = $this->migrationPath . DIRECTORY_SEPARATOR . $downFile;

		if(!\file_exists($filename))
		{
			$this->stdout("*** No such file: $filename\n\n", Console::FG_RED);
			return false;
		}

		$sql = \file_get_contents( $this->migrationPath . DIRECTORY_SEPARATOR . $downFile);
        $start = microtime(true);

		if($this->executeSql($sql))
		{
			$this->removeMigrationHistory($upFile);
			$time = microtime(true) - $start;
			$this->stdout("*** successed execute $downFile (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
			return true;
		}
		else
		{
            $time = microtime(true) - $start;
            $this->stdout("*** failed execute $downFile (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
            return false;
		}
    }
}
