<?php

namespace app\commands;


use Yii;
use yii\helpers\Console;
use yii\console\Exception;
use yii\console\ExitCode;


/**
 * Migration tool using sql-files ...up.sql and ...down.sql
 *
 *
 * File naming example:
 *
 * ```
 * - 01_create_table.up.sql
 * - 01_create_table.down.sql
 * - 02_insert_data.up.sql
 * - 02_insert_data.down.sql
 * ```
 *
 * To sort files well, you can add as many zeros to the beginning of the file name as needed.
 */
class MigrateController extends MigrateBaseController
{
    /**
     * Displays the migration history.
     *
     * This command will show the list of migrations that have been applied
     * so far. For example,
     *
     * ```
     * yii migrate/history     # showing the last 10 migrations
     * yii migrate/history 5   # showing the last 5 migrations
     * yii migrate/history all # showing the whole history
     * ```
     *
     * @param integer $limit the maximum number of migrations to be displayed.
     * If it is "all", the whole migration history will be displayed.
     * @throws \yii\console\Exception if invalid limit value passed
     */
    public function actionHistory($limit = 10)
    {
        if ($limit === 'all')
		{
            $limit = null;
        }
		else
		{
            $limit = (int) $limit;
            if ($limit < 1)
			{
				$this->stdout("The limit must be greater than 0.\n", Console::FG_RED);
				return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations))
		{
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
        }
		else
		{
            $n = count($migrations);
            if ($limit > 0)
			{
                $this->stdout("Showing the last $n applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            }
			else
			{
                $this->stdout("Total $n " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n", Console::FG_YELLOW);
            }

			foreach ($migrations as $migration)
			{
                $this->stdout("\t(" . $migration['created_at'] . ') ' . $migration['version'] . " : " . $migration['name'] . "\n");
            }
        }
    }

	/**
	 * Displays the un-applied new migrations.
	 *
	 * This command will show the new migrations that have not been applied.
	 * For example,
	 *
	 * ```
	 * yii migrate/new     # showing the first 10 new migrations
	 * yii migrate/new 5   # showing the first 5 new migrations
	 * yii migrate/new all # showing all new migrations
	 * ```
	 *
	 * @param integer $limit the maximum number of new migrations to be displayed.
	 * If it is `all`, all available new migrations will be displayed.
	 * @throws \yii\console\Exception if invalid limit value passed
	 */
	public function actionNew($limit = 10)
	{
		if ($limit === 'all')
		{
			$limit = null;
		}
		else
		{
			$limit = (int) $limit;
			if ($limit < 1)
			{
				$this->stdout("The limit must be greater than 0.\n", Console::FG_RED);
				return ExitCode::UNSPECIFIED_ERROR;
			}
		}

		$migrationFiles = $this->getNewMigrationFiles();

		if (empty($migrationFiles))
		{
			$this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
		}
		else
		{
			$n = count($migrationFiles);
			if ($limit && $n > $limit)
			{
				$migrationFiles = array_slice($migrationFiles, 0, $limit);
				$this->stdout("Showing $limit out of $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
			}
			else
			{
				$this->stdout("Found $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
			}

			foreach ($migrationFiles as $migrationFile)
			{
				$this->stdout("\t" . $migrationFile . "\n");
			}
		}
	}

    /**
     * Upgrades the application by applying new migrations.
     * For example,
     *
     * ```
     * yii migrate     # apply all new migrations
     * yii migrate 3   # apply the first 3 new migrations
     * ```
     *
     * @param integer $limit the number of new migrations to be applied. If 0, it means
     * applying all available new migrations.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionUp($limit = null)
    {
		if($limit === null)
		{
			$limit = 0;
		}
		else
		{
			$limit = (int)$limit;
			if ($limit < 1)
			{
				$this->stdout("The limit must be greater than 0.\n", Console::FG_RED);
				return ExitCode::UNSPECIFIED_ERROR;
			}
		}

        $migrationFiles = $this->getNewMigrationFiles();
        if (empty($migrationFiles))
		{
            $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
			return ExitCode::UNSPECIFIED_ERROR;
        }

        $total = count($migrationFiles);
        if ($limit > 0)
		{
            $migrationFiles = array_slice($migrationFiles, 0, $limit);
        }

        $n = count($migrationFiles);
        if ($n === $total)
		{
            $this->stdout("Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
        }
		else
		{
            $this->stdout("Total $n out of $total new " . ($total === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
        }

		$versionedFiles = [];
		$isDirty = false;
        foreach ($migrationFiles as $migrationFile)
		{
			$version = (int)$migrationFile;
			if(!isset($versionedFiles[$version]))
			{
				$versionedFiles[$version] = $migrationFile;

				$this->stdout("\t$migrationFile\n");
			}
			else
			{
				$this->stdout("\t$migrationFile\t(duplicate version in filename)\n", Console::FG_RED);
				$isDirty = true;
			}
        }
        $this->stdout("\n");

		if($isDirty)
		{
			$this->stdout("\nThere are duplicate versions in the list of migration files. Please correct version numbers.\n", Console::FG_RED);
			return ExitCode::UNSPECIFIED_ERROR;
		}

        $applied = 0;
        if($this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . '?'))
		{
            foreach ($migrationFiles as $upFile)
			{
                if (!$this->migrateUp($upFile))
				{
                    $this->stdout("\n$applied from $n " . ($applied === 1 ? 'migration was' : 'migrations were') ." applied.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

					return ExitCode::UNSPECIFIED_ERROR;
                }
                $applied++;
            }

            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') ." applied.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated up successfully.\n", Console::FG_GREEN);
        }
    }




    /**
     * Downgrades the application by reverting old migrations.
     * For example,
     *
     * ```
     * yii migrate/down     # revert the last migration
     * yii migrate/down 3   # revert the last 3 migrations
     * yii migrate/down all # revert all migrations
     * ```
     *
     * @param integer $limit the number of migrations to be reverted. Defaults to 1,
     * meaning the last applied migration will be reverted.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionDown($limit = 1)
    {
        if ($limit === 'all')
		{
            $limit = null;
        }
		else
		{
            $limit = (int)$limit;
            if ($limit < 1)
			{
				$this->stdout("The limit must be greater than 0.\n", Console::FG_RED);
				return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations))
		{
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
			return ExitCode::OK;
        }

        $n = count($migrations);
        $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be reverted:\n", Console::FG_YELLOW);
        foreach ($migrations as $migration) {
            $this->stdout("\t" .$migration['version'] .' : '. $migration['name'] . "\n");
        }
        $this->stdout("\n");

        $reverted = 0;
        if ($this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . '?'))
		{
            foreach ($migrations as $migration)
			{
                if (!$this->migrateDown( $migration['name'] ))
				{
                    $this->stdout("\n$reverted from $n " . ($reverted === 1 ? 'migration was' : 'migrations were') ." reverted.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);

					return ExitCode::UNSPECIFIED_ERROR;
                }
                $reverted++;
            }
            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') ." reverted.\n", Console::FG_GREEN);
            $this->stdout("\nMigrated down successfully.\n", Console::FG_GREEN);
        }
    }



    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * yii migrate/redo     # redo the last applied migration
     * yii migrate/redo 3   # redo the last 3 applied migrations
     * yii migrate/redo all # redo all migrations
     * ```
     *
     * @param integer $limit the number of migrations to be redone. Defaults to 1,
     * meaning the last applied migration will be redone.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionRedo($limit = 1)
    {
        if ($limit === 'all')
		{
            $limit = null;
        }
		else
		{
            $limit = (int)$limit;
            if ($limit < 1)
			{
				$this->stdout("The limit must be greater than 0.\n", Console::FG_RED);
				return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations))
		{
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
			return ExitCode::OK;
        }

        $n = count($migrations);
        $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n", Console::FG_YELLOW);
        foreach ($migrations as $migration)
		{
            $this->stdout("\t".$migration['name']."\n");
        }
        $this->stdout("\n");

        if ($this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . '?'))
		{
            foreach ($migrations as $migration)
			{
                if (!$this->migrateDown($migration['name']))
				{
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
					return ExitCode::UNSPECIFIED_ERROR;
                }
            }
            foreach (array_reverse($migrations) as $migration)
			{
                if (!$this->migrateUp($migration['name']))
				{
                    $this->stdout("\nMigration failed. The rest of the migrations migrations are canceled.\n", Console::FG_RED);
					return ExitCode::UNSPECIFIED_ERROR;
                }
            }
            $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') ." redone.\n", Console::FG_GREEN);
            $this->stdout("\nMigration redone successfully.\n", Console::FG_GREEN);
        }
    }




    /**
     * Upgrades or downgrades till the specified version.
     *
     * For example,
     *
     * ```
     * yii migrate/to 5                 # using number of version
     * ```
     *
     * @param string $version number of version.
     * @throws Exception if the version argument is invalid.
     */
	public function actionTo($version)
	{
		$version = (int)$version;

        if ($version < 1)
		{
			$this->stdout("The version must be greater than 0.\n", Console::FG_RED);
			return ExitCode::UNSPECIFIED_ERROR;
		}

		$migrations = $this->getMigrationHistory(1);

		if(empty($migrations))
		{
			$limit = $this->calcLimitUp($version);
			if($limit === false)
			{
				$this->stdout("Migration file for new version not found.\n", Console::FG_RED);
				return ExitCode::UNSPECIFIED_ERROR;
			}
			else
			{
				return $this->actionUp($limit);
			}
		}
		else
		{
			$lastVersion = (int)$migrations[0]['version'];

			if($version == $lastVersion)
			{
				$this->stdout("New version matches current version.\n", Console::FG_GREEN);
				return ExitCode::UNSPECIFIED_ERROR;
			}
			elseif($version > $lastVersion)
			{
				$limit = $this->calcLimitUp($version);
				if($limit === false)
				{
					$this->stdout("Migration file for new version not found.\n", Console::FG_RED);
					return ExitCode::UNSPECIFIED_ERROR;
				}
				else
				{
					return $this->actionUp($limit);
				}
			}
			else
			{
				$limit = $this->calcLimitDown($version);

				if($limit === false)
				{
					$this->stdout("There is no such version in the migration history.\n", Console::FG_RED);
					return ExitCode::UNSPECIFIED_ERROR;
				}
				else
				{
					return $this->actionDown($limit);
				}
			}
		}
	}


	/**
	 * Calculates the version limit to upgrades.
	 * @param int $version
	 * @return int
	 */
	protected function calcLimitUp($version)
	{
		$migrationFiles = $this->getNewMigrationFiles();

		$versions = [];
		$isFound = false;
		foreach($migrationFiles as $migrationFile)
		{
			$fileVersion = (int)$migrationFile;
			if($version >= $fileVersion)
			{
				$versions[] = $migrationFile;
			}

			if($version == $fileVersion)
			{
				$isFound = true;
			}
		}

		if(!$isFound)
		{
			return false;
		}

		return count($versions);
	}


	/**
	 * Calculates the version limit to downgrades.
	 * @param int $version
	 * @return int
	 */
	protected function calcLimitDown($version)
	{
		$migrations = $this->getMigrationHistory();

		$versions = [];
		$isFound = false;
		foreach($migrations as $migrationRow)
		{
			if($version < $migrationRow['version'])
			{
				$versions[] = $migrationRow;
			}

			if($version == $migrationRow['version'])
			{
				$isFound = true;
			}
		}

		if(!$isFound)
		{
			return false;
		}

		return count($versions);
	}
}