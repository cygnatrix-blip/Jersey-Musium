<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Per-request SEO state. Controllers set it; layouts/partials/seo.php renders
 * <title>, meta description, canonical, Open Graph, Twitter and JSON-LD.
 */
final class Seo
{
    private static ?Seo $instance = null;

    public string $title = '';
    public string $description = '';
    public ?string $canonical = null;
    public ?string $image = null;
    public string $type = 'website';
    public string $robots = 'index,follow';
    public array $jsonLd = [];
    public array $breadcrumbs = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = str_limit($description, 160);
        return $this;
    }

    public function canonical(string $pathOrUrl): self
    {
        $this->canonical = str_starts_with($pathOrUrl, 'http') ? $pathOrUrl : url($pathOrUrl);
        return $this;
    }

    public function image(?string $url): self
    {
        if ($url) {
            $this->image = str_starts_with($url, 'http') ? $url : url($url);
        }
        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function noindex(): self
    {
        $this->robots = 'noindex,nofollow';
        return $this;
    }

    public function jsonLd(array $schema): self
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $schema;
        return $this;
    }

    /** @param array<array{0:string,1:?string}> $crumbs [label, path|null] */
    public function breadcrumbs(array $crumbs): self
    {
        $this->breadcrumbs = $crumbs;
        $items = [];
        foreach ($crumbs as $i => [$label, $path]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label] + ($path ? ['item' => url($path)] : []);
        }
        return $this->jsonLd(['@type' => 'BreadcrumbList', 'itemListElement' => $items]);
    }

    public function fullTitle(): string
    {
        $brand = (string) setting('store_name', config('app.name'));
        if ($this->title === '') {
            return $brand . ' – ' . setting('store_tagline', 'Authentic Football Jerseys Online');
        }
        return $this->title . ' | ' . $brand;
    }
}
