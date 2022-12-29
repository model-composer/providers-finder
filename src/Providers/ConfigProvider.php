<?php namespace Model\ProvidersFinder\Providers;

use Model\Config\AbstractConfigProvider;

class ConfigProvider extends AbstractConfigProvider
{
	public static function migrations(): array
	{
		return [
			[
				'version' => '0.3.0',
				'migration' => function (array $currentConfig, string $env) {
					if ($currentConfig) // Already existing
						return $currentConfig;

					return [
						'namespaces' => [],
					];
				},
			],
		];
	}
}
