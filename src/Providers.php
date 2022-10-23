<?php namespace Model\ProvidersFinder;

use Composer\InstalledVersions;

class Providers
{
	private static array $cache = [];

	public static function find(string $className, array $ignorePackages = []): array
	{
		if (!isset(self::$cache[$className])) {
			if (class_exists('\\Model\\Cache\\Cache')) {
				$cache = \Model\Cache\Cache::getCacheAdapter();
				self::$cache[$className] = $cache->get('model.providers-finder.' . $className, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($className) {
					$item->expiresAfter(3600);
					\Model\Cache\Cache::registerInvalidation('keys', ['model.providers-finder.' . $className]);
					return self::doFind($className);
				});
			} else {
				self::$cache[$className] = self::doFind($className);
			}
		}

		if (count($ignorePackages) === 0) {
			return self::$cache[$className];
		} else {
			$filtered = [];
			foreach (self::$cache[$className] as $provider) {
				if (!in_array($provider['package'], $ignorePackages))
					$filtered[] = $provider;
			}
			return $filtered;
		}
	}

	private static function doFind(string $className): array
	{
		$providers = [];
		$seen = [];

		foreach (InstalledVersions::getAllRawData() as $installedVersions) {
			foreach ($installedVersions['versions'] as $package => $packageData) {
				if (str_starts_with($package, 'model/')) {
					if (in_array($package, $seen))
						continue;

					$seen[] = $package;

					$namespaceName = ucfirst(preg_replace_callback('/[-_](.)/', function ($matches) {
						return strtoupper($matches[1]);
					}, substr($package, 6)));

					$fullClassName = '\\Model\\' . $namespaceName . '\\' . $className;
					if (class_exists($fullClassName)) {
						$providers[$fullClassName] = [
							'package' => $package,
							'packageData' => $packageData,
							'provider' => $fullClassName,
						];
					}
				}
			}
		}

		// ModEl 3 modules
		if (defined('INCLUDE_PATH') and is_dir(INCLUDE_PATH . 'model')) {
			foreach (glob(INCLUDE_PATH . 'model' . DIRECTORY_SEPARATOR . '*') as $module_dir) {
				if (file_exists($module_dir . DIRECTORY_SEPARATOR . $className . '.php')) {
					$module_name = explode(DIRECTORY_SEPARATOR, $module_dir);
					$module_name = array_reverse($module_name)[0];
					$fullClassName = '\\Model\\' . $module_name . '\\' . $className;
					$providers[$fullClassName] = [
						'package' => $module_name,
						'packageData' => null,
						'provider' => $fullClassName,
					];
				}
			}
		}

		return array_values($providers);
	}
}
