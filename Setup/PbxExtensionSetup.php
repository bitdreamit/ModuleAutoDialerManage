<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleAutoDialerManage\Setup;

use MikoPBX\Modules\Setup\PbxExtensionSetupBase;


/**
 * Class PbxExtensionSetup
 * Module installer and uninstaller
 *
 * @package Modules\ModuleAutoDialerManage\Setup
 */
class PbxExtensionSetup extends PbxExtensionSetupBase
{

    /**
     * PbxExtensionSetup constructor.
     *
     * @param string $moduleUniqueID - the unique module identifier
     */
    public function __construct(string $moduleUniqueID)
    {
        parent::__construct($moduleUniqueID);

    }

    /**
     * Creates database structure according to models annotations
     *
     * If it necessary, it fills some default settings, and change sidebar menu item representation for this module
     *
     * After installation it registers module on PbxExtensionModules model
     *
     *
     * @return bool result of installation
     */
    public function installDB(): bool
    {
        $result = $this->createSettingsTableByModelsAnnotations();

        if ($result) {
            $result = $this->registerNewModule();
        }

        if ($result) { $this->transferOldSettings(); }
        if ($result) {
            $result = $this->addToSidebar();
        }

        return $result;
    }

    /**
     * Create folders on PBX system and apply rights
     *
     * @return bool result of installation
     */
    public function installFiles(): bool
    {
        return parent::installFiles();
    }

    /**
     * Bit Dream IT extension: registers sidebar menu items for ALL module pages.
     * Pages: main, campaigns, dashboard, blacklist, results, polling-results, audio.
     */
    public function addToSidebar(): bool
    {
        $menuItems = [
            [
                'uniqid'        => $this->moduleUniqueID,
                'group'         => 'routing',
                'iconClass'     => 'volume control phone',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-campaigns',
                'group'         => 'routing',
                'iconClass'     => 'list',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}Campaigns",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-dashboard',
                'group'         => 'routing',
                'iconClass'     => 'dashboard',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}Dashboard",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-results',
                'group'         => 'routing',
                'iconClass'     => 'phone',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}Results",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-polling-results',
                'group'         => 'routing',
                'iconClass'     => 'list ul',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}PollingResults",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-audio',
                'group'         => 'routing',
                'iconClass'     => 'file audio',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}Audio",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-blacklist',
                'group'         => 'routing',
                'iconClass'     => 'ban',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}Blacklist",
                'showAtSidebar' => true,
            ],
            [
                'uniqid'        => $this->moduleUniqueID . '-apiguide',
                'group'         => 'routing',
                'iconClass'     => 'code',
                'caption'       => "Breadcrumb{$this->moduleUniqueID}ApiGuide",
                'showAtSidebar' => true,
            ],
        ];

        foreach ($menuItems as $item) {
            $menuSettingsKey = "AdditionalMenuItem{$item['uniqid']}";
            $menuSettings    = \MikoPBX\Common\Models\PbxSettings::findFirstByKey($menuSettingsKey);
            if ($menuSettings === null) {
                $menuSettings      = new \MikoPBX\Common\Models\PbxSettings();
                $menuSettings->key = $menuSettingsKey;
            }
            $menuSettings->value = json_encode($item, JSON_UNESCAPED_UNICODE);
            $menuSettings->save();
        }
        return true;
    }

    /**
     * Unregister module on PbxExtensionModules,
     * Makes data backup if $keepSettings is true
     *
     * Before delete module we can do some soft delete changes, f.e. change forwarding rules i.e.
     *
     * @param  $keepSettings bool creates backup folder with module settings
     *
     * @return bool uninstall result
     */
    public function unInstallDB($keepSettings = false): bool
    {
        return parent::unInstallDB($keepSettings);
    }

    /**
     * Transfer (copy) settings from the original ModuleAutoDialer module's
     * database tables into this ModuleAutoDialerManage module's tables.
     *
     * Copies ALL rows from the original module's DB tables into the new
     * module's prefixed tables. Old tables are intentionally NOT dropped,
     * so the original module's data remains intact in case you want to
     * revert or run both modules side-by-side (the dialplan contexts have
     * been renamed to dialer-manage-* to avoid conflicts).
     *
     * Safe to run multiple times: rows are skipped if a row with the same id
     * already exists in the new table.
     */
    protected function transferOldSettings(): void
    {
        $migrations = [
            ['old' => 'm_ModuleAutoDialer',                  'new' => 'm_ModuleAutoDialerManage'],
            ['old' => 'm_Clients',                           'new' => 'm_ModuleAutoDialerManage_Clients'],
            ['old' => 'm_ClientsPhones',                     'new' => 'm_ModuleAutoDialerManage_ClientsPhones'],
            ['old' => 'm_ClientsProperties',                 'new' => 'm_ModuleAutoDialerManage_ClientsProperties'],
            ['old' => 'm_DialerExtensions',                  'new' => 'm_ModuleAutoDialerManage_DialerExtensions'],
            ['old' => 'm_Tasks',                             'new' => 'm_ModuleAutoDialerManage_Tasks'],
            ['old' => 'm_TaskResults',                       'new' => 'm_ModuleAutoDialerManage_TaskResults'],
            ['old' => 'm_Polling',                           'new' => 'm_ModuleAutoDialerManage_Polling'],
            ['old' => 'm_PolingResults',                     'new' => 'm_ModuleAutoDialerManage_PolingResults'],
            ['old' => 'm_Question',                          'new' => 'm_ModuleAutoDialerManage_Question'],
            ['old' => 'm_QuestionActions',                   'new' => 'm_ModuleAutoDialerManage_QuestionActions'],
            ['old' => 'm_AudioFiles',                        'new' => 'm_ModuleAutoDialerManage_AudioFiles'],
        ];

        foreach ($migrations as $migration) {
            if (!$this->db->tableExists($migration['old'])) {
                continue;
            }
            try {
                $oldRows = $this->db->fetchAll(
                    "SELECT * FROM `{$migration['old']}`",
                    \Phalcon\Db\Enum::FETCH_ASSOC
                );
                if (empty($oldRows)) {
                    continue;
                }
                $columns = array_keys($oldRows[0]);
                $colList = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
                $placeHolders = implode(', ', array_fill(0, count($columns), '?'));

                $copied = 0;
                $skipped = 0;
                foreach ($oldRows as $row) {
                    // Skip if a row with this id already exists in the new table
                    if (isset($row['id'])) {
                        $existing = $this->db->fetchOne(
                            "SELECT 1 FROM `{$migration['new']}` WHERE `id` = ? LIMIT 1",
                            \Phalcon\Db\Enum::FETCH_ASSOC,
                            [$row['id']]
                        );
                        if ($existing) {
                            $skipped++;
                            continue;
                        }
                    }
                    $this->db->execute(
                        "INSERT INTO `{$migration['new']}` ({$colList}) VALUES ({$placeHolders})",
                        array_values($row)
                    );
                    $copied++;
                }
                $this->messges[] = sprintf(
                    'Migrated %d rows (skipped %d duplicates) from %s to %s',
                    $copied,
                    $skipped,
                    $migration['old'],
                    $migration['new']
                );
            } catch (\Throwable $e) {
                $this->messges[] = sprintf(
                    'Error migrating %s -> %s: %s',
                    $migration['old'],
                    $migration['new'],
                    $e->getMessage()
                );
            }
        }
    }


}