<?php namespace Model\ProvidersFinder;

abstract class AbstractProvider
{
	public static function getDependencies(): array
	{
		return [];
	}
}
