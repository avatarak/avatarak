export const DEFAULT_BASE_URL: string;
export const MAX_BATCH: number;

export interface VerifiedAccount { network: 'website' | 'github' | 'telegram' | (string & {}); identifier: string; url: string }
export interface ProfileLink { label: string; url: string; description?: string; image?: string }

export interface Profile {
    hash: string;
    username: string;
    profile_url: string;
    name_fa: string | null;
    name_en: string | null;
    headline: string | null;
    location: string | null;
    bio: string | null;
    website: string | null;
    /** network → handle, e.g. { github: 'octocat' } */
    socials: Record<string, string>;
    links: ProfileLink[];
    verified_accounts: VerifiedAccount[];
    avatar_url: string | null;
}

export type DefaultImage = 'mp' | 'identicon' | 'blank' | '404';
export interface AvatarOptions { size?: number; default?: DefaultImage; baseUrl?: string }

export class ApiError extends Error { status: number; body: unknown }
export class AuthenticationError extends ApiError {}
export class RateLimitError extends ApiError { retryAfter: number }

export function hashEmail(emailOrHash: string): Promise<string>;
export function avatarUrl(emailOrHash: string, opts?: AvatarOptions): Promise<string>;
export function avatarUrlFromHash(hash: string, opts?: AvatarOptions): string;

export interface AvatarakOptions { apiKey?: string; baseUrl?: string; fetch?: typeof fetch; timeoutMs?: number }

export class Avatarak {
    constructor(options?: AvatarakOptions);
    hash(emailOrHash: string): Promise<string>;
    avatarUrl(emailOrHash: string, opts?: Omit<AvatarOptions, 'baseUrl'>): Promise<string>;
    /** null when there is no public profile for this address */
    profile(emailOrHash: string): Promise<Profile | null>;
    lookup(emailsOrHashes: Iterable<string>): Promise<Record<string, Profile | null>>;
}
