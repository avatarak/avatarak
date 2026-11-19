<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id', 'username', 'name_fa', 'name_en', 'bio', 'website', 'socials',
        'avatar_path', 'avatar_version', 'avatar_id', 'show_avatar', 'show_profile', 'allow_indexing', 'accent_color', 'section_order', 'headline', 'location', 'links', 'hidden_sections', 'show_link_images', 'theme', 'custom_sections', 'languages', 'primary_lang', 'headline_alt', 'location_alt', 'bio_alt', 'card_phone', 'card_phone_public', 'card_style',
    ];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
            'section_order' => 'array',
            'links' => 'array',
            'hidden_sections' => 'array',
            'custom_sections' => 'array',
            'show_link_images' => 'boolean',
            'show_avatar' => 'boolean',
            'show_profile' => 'boolean',
            'allow_indexing' => 'boolean',
            'card_phone_public' => 'boolean',
        ];
    }

    /** Publicly visible: switched on by the owner AND the account is not suspended. */
    public function scopeVisible($query)
    {
        return $query->where('show_profile', true)->whereHas('user', fn ($q) => $q->whereNull('suspended_at'));
    }

    public const CARD_STYLES = ['classic', 'band', 'bold'];

    public const LANGS = ['fa', 'en'];
    public const MODES = ['fa', 'en', 'both'];

    /** Changing the primary language swaps which of the two texts is stored as "base", so nothing is mislabeled. */
    public function swapPrimary(string $to): void
    {
        foreach (['headline', 'location', 'bio'] as $f) {
            [$this->attributes[$f], $this->attributes[$f.'_alt']] = [$this->attributes[$f.'_alt'] ?? null, $this->attributes[$f] ?? null];
        }
        $swap = fn (array $a, array $pairs) => collect($pairs)->reduce(function ($row, $pair) {
            [$x, $y] = $pair;
            $vx = $row[$x] ?? null;
            $vy = $row[$y] ?? null;
            unset($row[$x], $row[$y]);

            return $row + array_filter([$x => $vy, $y => $vx], fn ($v) => $v !== null && $v !== '');
        }, $a);
        $this->links = collect($this->links ?? [])->map(fn ($l) => $swap($l, [['label', 'label_alt'], ['description', 'description_alt']]))->all();
        $this->custom_sections = collect($this->custom_sections ?? [])->map(fn ($s) => $swap($s, [['title', 'title_alt'], ['body', 'body_alt']]))->all();
        \App\Models\GalleryImage::where('user_id', $this->user_id)->each(fn ($g) => $g->update(['caption' => $g->caption_alt, 'caption_alt' => $g->caption]));
        $this->primary_lang = $to;
    }

    /** The language this (localized) copy speaks; set by localized(). */
    public ?string $lang = null;

    /** On a localized copy: the untouched profile it was made from. */
    public ?Profile $source = null;

    /** The language the page speaks when nobody asked for another: the single language, or the primary one. */
    public function defaultLang(): string
    {
        return $this->languages === 'both' || ! in_array($this->languages, self::LANGS, true) ? ($this->primary_lang ?: 'fa') : $this->languages;
    }

    /** @return list<string> */
    public function servedLanguages(): array
    {
        return $this->languages === 'both' ? self::LANGS : [$this->defaultLang()];
    }

    /** Which language to show: a visitor's choice only counts on a bilingual profile. */
    public function chooseLang(?string $requested): string
    {
        return $this->languages === 'both' && in_array($requested, self::LANGS, true) ? $requested : $this->defaultLang();
    }

    /**
     * One text in the wanted language. $base is written in primary_lang, $alt in the other language.
     * A bilingual profile falls back to the other language when one is empty; a single-language profile never mixes.
     */
    public function pick(?string $base, ?string $alt, string $lang): ?string
    {
        $own = $lang === ($this->primary_lang ?: 'fa') ? $base : $alt;
        $other = $lang === ($this->primary_lang ?: 'fa') ? $alt : $base;
        $own = ($own === null || $own === '') ? null : $own;

        return $own ?? ($this->languages === 'both' && $other !== '' ? $other : null);
    }

    /** A read-only copy whose texts are already in $lang, so every renderer can just use ->headline, ->bio … as before. */
    public function localized(?string $lang = null): static
    {
        if ($this->source) {
            return $this->source->localized($lang);
        }
        $lang ??= $this->defaultLang();
        $c = clone $this;
        $c->lang = $lang;
        $c->source = $this;
        $c->setRawAttributes($this->getAttributes(), true);
        foreach (['headline', 'location', 'bio'] as $f) {
            $c->setAttribute($f, $this->pick($this->getAttribute($f), $this->getAttribute($f.'_alt'), $lang));
        }
        $c->setAttribute('links', collect($this->links ?? [])->map(function ($l) use ($lang) {
            $l['label'] = $this->pick($l['label'] ?? null, $l['label_alt'] ?? null, $lang);
            $d = $this->pick($l['description'] ?? null, $l['description_alt'] ?? null, $lang);
            unset($l['description'], $l['label_alt'], $l['description_alt']);

            return $d !== null ? $l + ['description' => $d] : $l;
        })->filter(fn ($l) => ! empty($l['label']))->values()->all());
        $c->setAttribute('custom_sections', collect($this->custom_sections ?? [])->map(fn ($s) => [
            'title' => $this->pick($s['title'] ?? null, $s['title_alt'] ?? null, $lang),
            'body' => $this->pick($s['body'] ?? null, $s['body_alt'] ?? null, $lang),
        ])->filter(fn ($s) => $s['title'] && $s['body'])->values()->all());

        return $c;
    }

    public const THEMES = ['gradient', 'dark', 'paper', 'plain'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
