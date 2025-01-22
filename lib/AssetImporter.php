<?php

namespace FriendsOfRedaxo\AssetImport;

use Exception;
use Psr\Log\LogLevel;
use rex_autoload;
use rex_exception;
use rex_logger;

class AssetImporter
{
    private static array $providers = [];

    /**
     * Registriert einen neuen Provider.
     *
     * @param string $class Provider-Klassenname
     * @throws rex_exception wenn die Klasse nicht existiert oder das Interface nicht implementiert
     */
    public static function registerProvider(string $class): void
    {
        if (!class_exists($class)) {
            throw new rex_exception('Provider class does not exist: ' . $class);
        }

        $provider = new $class();
        if (!$provider instanceof Provider\ProviderInterface) {
            throw new rex_exception('Provider must implement ProviderInterface: ' . $class);
        }

        // Überprüfe ob der Provider korrekt konfiguriert werden kann
        if (!method_exists($provider, 'getConfigFields')) {
            throw new rex_exception('Provider must implement getConfigFields method: ' . $class);
        }

        // Überprüfe ob die Konfigurationsfelder das richtige Format haben
        $configFields = $provider->getConfigFields();
        foreach ($configFields as $field) {
            if (!isset($field['name']) || !isset($field['type']) || !isset($field['label'])) {
                throw new rex_exception('Invalid config field format in provider: ' . $class);
            }
        }

        self::$providers[$provider->getName()] = $class;

        // Log erfolgreiche Provider-Registrierung
        if (class_exists('\rex_logger')) {
            rex_logger::factory()->log(LogLevel::INFO,
                'Provider registered successfully',
                ['provider' => $provider->getName(), 'class' => $class],
            );
        }
    }

    /**
     * Registriert alle Provider aus einem bestimmten Namespace.
     *
     * @param string $namespace Namespace der Provider-Klassen
     */
    public static function registerProvidersFromNamespace(string $namespace): void
    {
        foreach (rex_autoload::getClasses() as $class) {
            if (str_starts_with($class, $namespace)
                && is_subclass_of($class, Provider\ProviderInterface::class)) {
                try {
                    self::registerProvider($class);
                } catch (Exception $e) {
                    if (class_exists('\rex_logger')) {
                        rex_logger::factory()->log(LogLevel::ERROR,
                            'Failed to register provider from namespace',
                            [
                                'namespace' => $namespace,
                                'class' => $class,
                                'error' => $e->getMessage(),
                            ],
                        );
                    }
                }
            }
        }
    }

    /**
     * Gibt eine Provider-Instanz zurück.
     *
     * @param string $id Provider-ID
     * @return Provider\ProviderInterface|null Provider-Instanz oder null wenn nicht gefunden
     */
    public static function getProvider(string $id): ?Provider\ProviderInterface
    {
        if (!isset(self::$providers[$id])) {
            if (class_exists('\rex_logger')) {
                rex_logger::factory()->log(LogLevel::WARNING,
                    'Provider not found',
                    ['provider_id' => $id],
                );
            }
            return null;
        }

        try {
            $class = self::$providers[$id];
            $provider = new $class();

            // Überprüfe ob die Konfiguration gültig ist
            if (!$provider->isConfigured()) {
                if (class_exists('\rex_logger')) {
                    rex_logger::factory()->log(LogLevel::WARNING,
                        'Provider not configured properly',
                        ['provider_id' => $id],
                    );
                }
            }

            return $provider;
        } catch (Exception $e) {
            if (class_exists('\rex_logger')) {
                rex_logger::factory()->log(LogLevel::ERROR,
                    'Failed to instantiate provider',
                    [
                        'provider_id' => $id,
                        'error' => $e->getMessage(),
                    ],
                );
            }
            return null;
        }
    }

    /**
     * Gibt alle registrierten Provider zurück.
     *
     * @return array Array von Provider-Klassen
     */
    public static function getProviders(): array
    {
        return self::$providers;
    }

    /**
     * Überprüft ob ein Provider registriert ist.
     *
     * @param string $id Provider-ID
     */
    public static function hasProvider(string $id): bool
    {
        return isset(self::$providers[$id]);
    }

    /**
     * Entfernt einen Provider.
     *
     * @param string $id Provider-ID
     * @return bool true wenn der Provider entfernt wurde
     */
    public static function removeProvider(string $id): bool
    {
        if (isset(self::$providers[$id])) {
            unset(self::$providers[$id]);

            if (class_exists('\rex_logger')) {
                rex_logger::factory()->log(LogLevel::INFO,
                    'Provider removed',
                    ['provider_id' => $id],
                );
            }

            return true;
        }
        return false;
    }
}
