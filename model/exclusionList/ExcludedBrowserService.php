<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2021-2023 (original work) Open Assessment Technologies SA.
 */

declare(strict_types=1);

namespace oat\taoClientDiagnostic\model\exclusionList;

use core_kernel_classes_Class;
use core_kernel_classes_Property;

class ExcludedBrowserService extends ExclusionListService
{
    public const SERVICE_ID = 'taoClientDiagnostic/ExcludedBrowserService';

    public const ROOT_CLASS = 'http://www.tao.lu/Ontologies/TAODelivery.rdf#ExcludedBrowser';
    public const LIST_CLASS = 'http://www.tao.lu/Ontologies/TAODelivery.rdf#BrowsersList';
    public const EXCLUDED_NAME = 'http://www.tao.lu/Ontologies/TAODelivery.rdf#ExcludedBrowserName';
    public const EXCLUDED_VERSION = 'http://www.tao.lu/Ontologies/TAODelivery.rdf#ExcludedBrowserVersion';

    /**
     * Get the root class for excluded Browser
     *
     * @return core_kernel_classes_Class
     */
    public function getRootClass()
    {
        return $this->getClass(self::ROOT_CLASS);
    }

    public function getNameProperty(): core_kernel_classes_Property
    {
        return $this->getProperty($this->getNamePropertyUri());
    }

    public function getNamePropertyUri(): string
    {
        return self::EXCLUDED_NAME;
    }

    public function getVersionProperty(): core_kernel_classes_Property
    {
        return $this->getProperty($this->getVersionPropertyUri());
    }

    public function getVersionPropertyUri(): string
    {
        return self::EXCLUDED_VERSION;
    }

    protected function getListClass(): core_kernel_classes_Class
    {
        return $this->getClass(self::LIST_CLASS);
    }
}
