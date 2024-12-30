<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

interface ProviderInterface
{
    public function getName(): string;
    public function getTitle(): string;
    public function getIcon(): string;
    public function isConfigured(): bool;
    public function getConfigFields(): array;
    public function search(string $query, int $page = 1, array $options = []): array;
    public function import(string $url, string $filename): bool;
    public function getDefaultOptions(): array;
}
