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
 * Copyright (c) 2021 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace oat\taoClientDiagnostic\model\exclusionList;

use core_kernel_classes_Class;
use core_kernel_classes_Property;
use core_kernel_classes_Resource;
use oat\generis\model\OntologyRdfs;
use oat\tao\model\OntologyClassService;

abstract class ExclusionListService extends OntologyClassService
{
    /** @var array */
    private $names;

    /** @var array */
    private $excluded;

    abstract public function getNameProperty(): core_kernel_classes_Property;

    abstract public function getNamePropertyUri(): string;

    abstract public function getVersionProperty(): core_kernel_classes_Property;

    abstract public function getVersionPropertyUri(): string;

    abstract protected function getListClass(): core_kernel_classes_Class;

    public function getListNames(): array
    {
        if ($this->names === null) {
            $nameInstances = $this->getNameProperty()->getRange()->getInstances();
            $this->names = [];
            foreach ($nameInstances as $nameInstance) {
                $this->names[strtolower($nameInstance->getLabel())] = $nameInstance->getUri();
            }
        }

        return $this->names;
    }

    public function getExclusionsList(): array
    {
        if ($this->excluded === null) {
            $instances = $this->getRootClass()->getInstances(true);
            $this->excluded = [];
            foreach ($instances as $instance) {
                $properties = $instance->getPropertiesValues([
                    $this->getNameProperty(),
                    $this->getVersionProperty()
                ]);

                $excludedNameProperty = current($properties[$this->getNamePropertyUri()]);
                if ($excludedNameProperty) {
                    $excludedName = strtolower($excludedNameProperty->getLabel());
                    $excludedVersion = (string)current($properties[$this->getVersionPropertyUri()]);

                    $this->excluded[$excludedName][] = $excludedVersion;
                }
            }
        }

        return $this->excluded;
    }

    public function getExclusionsByName($name): array
    {
        return $this->getRootClass()->searchInstances(
            [OntologyRdfs::RDFS_LABEL => $name],
            ['like' => false]
        );
    }

    public function getListDefinitionByName($name): ?core_kernel_classes_Resource
    {
        $results = $this->getListClass()->searchInstances(
            [OntologyRdfs::RDFS_LABEL => $name],
            ['like' => false]
        );

        return array_pop($results);
    }
}
