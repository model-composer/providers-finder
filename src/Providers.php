<?php namespace Model\ProvidersFinder;

use Composer\InstalledVersions;

class Providers
{
	public static function find(string $className): array
	{
		$providers = [];
		foreach (InstalledVersions::getAllRawData() as $installedVersions) {
			foreach ($installedVersions['versions'] as $package => $packageData) {
				if (str_starts_with($package, 'model/')) {
					$namespaceName = ucfirst(preg_replace_callback('/[-_](.)/', function ($matches) {
						return strtoupper($matches[1]);
					}, substr($package, 6)));

					$fullClassName = '\\Model\\' . $namespaceName . '\\' . $className;
					if (class_exists($fullClassName)) {
						$providers[] = [
							'package' => $package,
							'packageData' => $packageData,
							'provider' => $fullClassName,
						];
					}
				}
			}
		}

		return $providers;
	}
}
