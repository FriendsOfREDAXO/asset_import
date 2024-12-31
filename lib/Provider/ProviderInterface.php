<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

interface ProviderInterface
{
    /**
     * Get unique provider identifier
     */
    public function getName(): string;
    
    /**
     * Get display name
     */
    public function getTitle(): string;
    
    /**
     * Get FontAwesome icon identifier
     */
    public function getIcon(): string;
    
    /**
     * Check if provider is configured
     */
    public function isConfigured(): bool;
    
    /**
     * Get configuration fields
     */
    public function getConfigFields(): array;
    
    /**
     * Search for assets
     */
    public function search(string $query, int $page = 1, array $options = []): array;
    
    /**
     * Import an asset
     */
    public function import(string $url, string $filename): bool;
    
    /**
     * Get default search options
     */
    public function getDefaultOptions(): array;
}
