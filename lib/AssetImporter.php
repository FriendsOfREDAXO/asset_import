<?php
namespace FriendsOfRedaxo\AssetImport;

class AssetImporter
{
    private static array $providers = [];

    public static function registerProvider(string $class): void
    {
        if (!class_exists($class)) {
            throw new \rex_exception('Provider class does not exist: ' . $class);
        }
        
        $provider = new $class();
        if (!$provider instanceof Provider\ProviderInterface) {
            throw new \rex_exception('Provider must implement ProviderInterface: ' . $class);
        }
        
        if ($provider->isConfigured()) {
            self::$providers[$provider->getName()] = $class;
        }
    }

    public static function registerProvidersFromNamespace(string $namespace): void
    {
        foreach (\rex_autoload::getClasses() as $class) {
            if (strpos($class, $namespace) === 0 && is_subclass_of($class, Provider\ProviderInterface::class)) {
                self::registerProvider($class);
            }
        }
    }

    public static function getProvider(string $id)
    {
        if (!isset(self::$providers[$id])) {
            return null;
        }
        $class = self::$providers[$id];
        return new $class();
    }

    public static function getProviders(): array
    {
        return self::$providers;
    }
}
