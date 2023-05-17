<?php namespace Model\ProvidersFinder;

use Composer\InstalledVersions;
use MJS\TopSort\Implementations\StringSort;
use Model\Config\Config;

class Providers
{
	private static array $cache = [];

	public static function find(string $className, array $ignorePackages = []): array
	{
		if (!isset(self::$cache[$className])) {
			if (class_exists('\\Model\\Cache\\Cache') and $className !== 'ConfigProvider') {
				$cache = \Model\Cache\Cache::getCacheAdapter();
				self::$cache[$className] = $cache->get('model.providers-finder.' . $className, function (\Symfony\Contracts\Cache\ItemInterface $item) use ($className) {
					$item->expiresAfter(3600 * 24);
					return self::doFind($className);
				});
			} else {
				self::$cache[$className] = self::doFind($className);
			}
		}

		$filtered = [];
		foreach (self::$cache[$className] as $provider) {
			if ($provider['force_require'])
				require_once($provider['force_require']);
			if (!in_array($provider['package'], $ignorePackages))
				$filtered[] = $provider;
		}

		return count($ignorePackages) === 0 ? self::$cache[$className] : $filtered;
	}

	private static function doFind(string $className): array
	{
		$namespaces = [];
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

					$namespaces[] = [
						'package' => $package,
						'path' => $packageData['install_path'],
						'name' => '\\Model\\' . $namespaceName,
					];
				}
			}
		}

		if ($className !== 'ConfigProvider') { // Prevents infinite loop
			$config = Config::get('providers-finder');
			foreach ($config['namespaces'] as $namespace) {
				$namespaces[] = [
					'package' => $namespace['package'] ?? null,
					'path' => $namespace['path'] ?? null,
					'name' => $namespace['name'],
				];
			}
		}

		$providers = [];
		foreach ($namespaces as $namespace) {
			$fullClassName = $namespace['name'] . '\\Providers\\' . $className;
			if (class_exists($fullClassName) and is_subclass_of($fullClassName, AbstractProvider::class)) {
				$dependencies = [];

				if (!empty($namespace['path'])) {
					$composerFile = json_decode(file_get_contents($namespace['path'] . DIRECTORY_SEPARATOR . 'composer.json'), true);

					foreach ($composerFile['require'] as $dependentPackage => $dependentPackageVersion) {
						if (str_starts_with($dependentPackage, 'model/'))
							$dependencies[] = $dependentPackage;
					}
				}

				foreach ($fullClassName::getDependencies() as $dependentPackage) {
					if (!in_array($dependentPackage, $dependencies))
						$dependencies[] = $dependentPackage;
				}

				$providers[$namespace['package'] ?? $fullClassName] = [
					'package' => $namespace['package'] ?? null,
					'provider' => $fullClassName,
					'dependencies' => $dependencies,
					'force_require' => null,
				];
			}
		}

		if (count($providers) > 0) {
			// I sort them by their respective dependencies (using topsort algorithm)
			$sorter = new StringSort;

			foreach ($providers as $package) {
				$dependencies = array_filter($package['dependencies'], function ($dependency) use ($providers) {
					return array_key_exists($dependency, $providers);
				});

				$sorter->add($package['package'] ?? $package['provider'], $dependencies);
			}

			$sorted = $sorter->sort();

			// Rebuild
			$newProviders = [];
			foreach ($sorted as $package)
				$newProviders[$providers[$package]['provider']] = $providers[$package];
			$providers = $newProviders;
		}

		// ModEl 3 modules
		if (defined('INCLUDE_PATH') and is_dir(INCLUDE_PATH . 'model')) {
			$modules_dirs = [];
			if (is_dir(INCLUDE_PATH . 'model'))
				$modules_dirs[] = INCLUDE_PATH . 'model';
			if (is_dir(INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'modules'))
				$modules_dirs[] = INCLUDE_PATH . 'app' . DIRECTORY_SEPARATOR . 'modules';

			foreach ($modules_dirs as $modules_dir) {
				foreach (glob($modules_dir . DIRECTORY_SEPARATOR . '*') as $module_dir) {
					$fullFilePath = $module_dir . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . $className . '.php';
					if (file_exists($fullFilePath)) {
						$module_name = explode(DIRECTORY_SEPARATOR, $module_dir);
						$module_name = array_reverse($module_name)[0];
						$fullClassName = '\\Model\\' . $module_name . '\\Providers\\' . $className;
						$providers[$fullClassName] = [
							'package' => $module_name,
							'provider' => $fullClassName,
							'dependencies' => [],
							'force_require' => $fullFilePath,
						];
					}
				}
			}
		}

		return array_values($providers);
	}
}
