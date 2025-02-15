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
 * Copyright (c) 2015-2021 (original work) Open Assessment Technologies SA;
 */

namespace oat\taoClientDiagnostic\model;

use common_exception_InconsistentData;
use common_exception_FileSystemError;
use common_exception_MissingParameter;
use oat\oatbox\service\ConfigurableService;
use oat\taoClientDiagnostic\model\exclusionList\ExcludedBrowserService;
use oat\taoClientDiagnostic\model\exclusionList\ExcludedOSService;
use oat\taoClientDiagnostic\model\SupportedList\SupportedListInterface;
use Sinergi\BrowserDetector\Browser;
use Sinergi\BrowserDetector\Os;

class CompatibilityChecker extends ConfigurableService
{
    public const SERVICE_ID = 'taoClientDiagnostic/CompatibilityChecker';

    public const COMPATIBILITY_NONE = 0;
    public const COMPATIBILITY_COMPATIBLE = 1;
    public const COMPATIBILITY_NOT_TESTED = 2;
    public const COMPATIBILITY_SUPPORTED = 3;
    public const COMPATIBILITY_NOT_SUPPORTED = 4;

    protected $compatibility;
    protected $supported;
    protected $excludedBrowsers;
    protected $excludedOS;

    /**
     * Extract compatibility file
     * @throws common_exception_FileSystemError
     */
    public function getCompatibilityList(): array
    {
        if ($this->compatibility === null) {
            $compatibilityFile = __DIR__ . '/../include/compatibility.json';

            if (!file_exists($compatibilityFile)) {
                throw new common_exception_FileSystemError('Unable to find the compatibility file');
            }
            $this->compatibility = json_decode(file_get_contents($compatibilityFile), true);
        }
        return $this->compatibility;
    }

    /**
     * Fetch the support list
     * @throws common_exception_FileSystemError
     * @throws common_exception_InconsistentData
     * @throws common_exception_MissingParameter
     */
    public function getSupportedList(): array
    {
        if ($this->supported == null) {
            /** @var SupportedListInterface $remoteSupportedListService */
            $remoteSupportedListService = $this->getServiceLocator()->get(SupportedListInterface::SERVICE_ID);
            $supportedList = $remoteSupportedListService->getList();

            if (!$supportedList) {
                throw new common_exception_InconsistentData('Unable to decode list of supported browsers');
            }

            $this->supported = array_map(function ($entry) {
                $entry['compatible'] = self::COMPATIBILITY_SUPPORTED;

                $entry['versions'] = array_merge(...array_map(static function (string $version): array {
                    return explode('-', $version);
                }, $entry['versions']));

                return $entry;
            }, $supportedList);
        }
        return $this->supported;
    }

    protected function filterVersion($version): string
    {
        return preg_replace('#(\.0+)+($|-)#', '', $version);
    }

    /**
     * Standard version_compare threats that  5.2 < 5.2.0, 5.2 < 5.2.1, ...
     */
    protected function versionCompare($ver1, $ver2): int
    {
        return version_compare($this->filterVersion($ver1), $this->filterVersion($ver2));
    }

    /**
     * Check if a version is greater or equal to the listed ones
     */
    protected function checkVersion($testedVersion, $versionList): bool
    {
        if (empty($versionList)) {
            return true;
        }

        foreach ($versionList as $version) {
            if ($this->versionCompare($testedVersion, $version) >= 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a version is excluded
     */
    protected function isExcluded($name, $version, $exclusionsList): bool
    {
        $name = strtolower($name);
        if (count($exclusionsList) && array_key_exists($name, $exclusionsList)) {
            $explodedVersion = explode('.', $version);
            $excludedVersions = $exclusionsList[$name];
            foreach ($excludedVersions as $excludedVersion) {
                if (empty($excludedVersion)) {
                    // any version is excluded
                    return true;
                }
                $explodedExcludedVersion = explode('.', $excludedVersion);
                if (array_slice($explodedVersion, 0, count($explodedExcludedVersion)) == $explodedExcludedVersion) {
                    // greedy or exact version is excluded
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks if a browser is excluded
     */
    public function isBrowserExcluded($name, $version): bool
    {
        if ($this->excludedBrowsers === null) {
            $service = $this->getServiceLocator()->get(ExcludedBrowserService::SERVICE_ID);
            $this->excludedBrowsers = $service->getExclusionsList();
        }
        return $this->isExcluded($name, $version, $this->excludedBrowsers);
    }

    /**
     * Checks if an OS is excluded
     */
    public function isOsExcluded($name, $version): bool
    {
        if ($this->excludedOS === null) {
            $service = $this->getServiceLocator()->get(ExcludedOSService::SERVICE_ID);
            $this->excludedOS = $service->getExclusionsList();
        }
        return $this->isExcluded($name, $version, $this->excludedOS);
    }

    /**
     * Checks if the client browser, and the OS, meet the requirements supplied in a validation list.
     * Returns a value corresponding to the COMPATIBILITY_* constants.
     * @throws common_exception_FileSystemError
     * @throws common_exception_InconsistentData
     * @throws common_exception_MissingParameter
     */
    public function isCompatibleConfig(): int
    {
        $clientDevice = $this->getOsDetector()->isMobile() ? 'mobile' : 'desktop';
        $clientOS = strtolower($this->getOsDetector()->getName());
        $clientOSVersion = $this->getOsDetector()->getVersion();
        $clientBrowser = strtolower($this->getBrowserDetector()->getName());
        $clientBrowserVersion = $this->getBrowserDetector()->getVersion();

        if (
            $this->isOsExcluded($clientOS, $clientOSVersion) ||
            $this->isBrowserExcluded($clientBrowser, $clientBrowserVersion)
        ) {
            return self::COMPATIBILITY_NOT_SUPPORTED;
        }

        $validationList = array_merge($this->getSupportedList(), $this->getCompatibilityList());

        foreach ($validationList as $entry) {
            if ($clientDevice !== $entry['device']) {
                continue;
            }

            if ($entry['os']) {
                if (strtolower($entry['os']) !== $clientOS) {
                    continue;
                }

                if ($entry['osVersion'] && $this->versionCompare($clientOSVersion, $entry['osVersion']) !== 0) {
                    continue;
                }
            }

            if (strtolower($entry['browser']) !== $clientBrowser) {
                continue;
            }

            if ($this->checkVersion($clientBrowserVersion, $entry['versions'])) {
                if (isset($entry['compatible'])) {
                    return $entry['compatible'];
                }
                return self::COMPATIBILITY_COMPATIBLE;
            }
        }

        return self::COMPATIBILITY_NOT_TESTED;
    }

    /**
     * Get the browser detector
     */
    protected function getBrowserDetector(): Browser
    {
        return new Browser();
    }

    /**
     * Get the operating system detector
     */
    protected function getOsDetector(): Os
    {
        return new Os();
    }
}
