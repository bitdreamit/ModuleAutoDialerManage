<?php
/*
 * ModuleAutoDialerManage - professional campaign dialer for MikoPBX
 * Copyright (C) 2025 Bit Dream IT
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleAutoDialerManage\Models;

use MikoPBX\Modules\Models\ModulesModelsBase;

/**
 * Do-Not-Call (DNC) blacklist.
 * Numbers in this table are NEVER dialed by WorkerDialer, regardless of
 * which campaign they belong to.
 *
 * @package Modules\ModuleAutoDialerManage\Models
 * @Indexes(
 *     [name='number', columns=['number'], type='unique']
 * )
 */
class Blacklist extends ModulesModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Phone number to block (digits only, normalized).
     * @Column(type="string", nullable=false)
     */
    public $number;

    /**
     * Optional reason / note for the blacklist entry.
     * @Column(type="string", nullable=true, default="")
     */
    public $reason = '';

    /**
     * Optional source: 'manual', 'complaint', 'regulator', 'auto-amd'.
     * @Column(type="string", nullable=true, default="manual")
     */
    public $source = 'manual';

    /**
     * Unix timestamp of when the entry was added.
     * @Column(type="integer", nullable=false)
     */
    public $createdAt;

    public function initialize(): void
    {
        $this->setSource('m_ModuleAutoDialerManage_Blacklist');
        parent::initialize();
    }

    public function beforeCreate(): void
    {
        if (empty($this->createdAt)) {
            $this->createdAt = time();
        }
        // Normalize: digits only
        $this->number = preg_replace('/\D/', '', (string)$this->number);
    }

    public function beforeSave(): void
    {
        $this->number = preg_replace('/\D/', '', (string)$this->number);
    }
}
