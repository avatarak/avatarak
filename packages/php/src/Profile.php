<?php

namespace Avatarak;

/** A public profile as returned by the REST API (field names match the API 1:1). */
final class Profile
{
    /**
     * @param  array<string,string>  $socials  network => handle, e.g. ['github' => 'octocat']
     * @param  list<array{label:string,url:string,description?:string,image?:string}>  $links
     * @param  list<array{network:string,identifier:string,url:string}>  $verifiedAccounts  only accounts the user proved they own
     */
    public function __construct(
        public readonly string $hash,
        public readonly string $username,
        public readonly string $profileUrl,
        public readonly ?string $nameFa,
        public readonly ?string $nameEn,
        public readonly ?string $headline,
        public readonly ?string $location,
        public readonly ?string $bio,
        public readonly ?string $website,
        public readonly array $socials,
        public readonly array $links,
        public readonly array $verifiedAccounts,
        public readonly ?string $avatarUrl,
    ) {}

    /** @param array<string,mixed> $d the "data" object of an API response */
    public static function fromArray(array $d): self
    {
        return new self(
            (string) ($d['hash'] ?? ''), (string) ($d['username'] ?? ''), (string) ($d['profile_url'] ?? ''),
            $d['name_fa'] ?? null, $d['name_en'] ?? null, $d['headline'] ?? null, $d['location'] ?? null,
            $d['bio'] ?? null, $d['website'] ?? null,
            (array) ($d['socials'] ?? []), (array) ($d['links'] ?? []), (array) ($d['verified_accounts'] ?? []),
            $d['avatar_url'] ?? null,
        );
    }

    /** Persian name if there is one, else English, else the username. */
    public function displayName(): string
    {
        return $this->nameFa ?: ($this->nameEn ?: $this->username);
    }

    public function isVerified(string $network): bool
    {
        foreach ($this->verifiedAccounts as $a) {
            if (($a['network'] ?? null) === $network) {
                return true;
            }
        }

        return false;
    }
}
