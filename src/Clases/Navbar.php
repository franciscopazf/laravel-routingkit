<?php

namespace Fp\FullRoute\Clases;

class Navbar
{
    public string $title;
    public string $description;
    public string $keywords;
    public string $icon;
    public string $visibleNavbar;
    public string $enabledNavbarHorizontal;

    public function __construct() {}

    public static function make(): self
    {
        return new static();
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }
    public function setKeywords(string $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }
    public function setVisibleNavbar(string $visibleNavbar): self
    {
        $this->visibleNavbar = $visibleNavbar;
        return $this;
    }
    public function setEnabledNavbarHorizontal(string $enabledNavbarHorizontal): self
    {
        $this->enabledNavbarHorizontal = $enabledNavbarHorizontal;
        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function getKeywords(): string
    {
        return $this->keywords;
    }
    public function getIcon(): string
    {
        return $this->icon;
    }
    public function getVisibleNavbar(): string
    {
        return $this->visibleNavbar;
    }
    public function getEnabledNavbarHorizontal(): string
    {
        return $this->enabledNavbarHorizontal;
    }
    public function getNavbar(): array
    {
        return [
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'keywords' => $this->getKeywords(),
            'icon' => $this->getIcon(),
            'visibleNavbar' => $this->getVisibleNavbar(),
            'enabledNavbarHorizontal' => $this->getEnabledNavbarHorizontal()
        ];
    }
    public function getNavbarArray(): array
    {
        return [
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'keywords' => $this->getKeywords(),
            'icon' => $this->getIcon(),
            'visibleNavbar' => $this->getVisibleNavbar(),
            'enabledNavbarHorizontal' => $this->getEnabledNavbarHorizontal()
        ];
    }
    public function getNavbarJson(): string
    {
        return json_encode($this->getNavbarArray());
    }
    public function getNavbarJsonPretty(): string
    {
        return json_encode($this->getNavbarArray(), JSON_PRETTY_PRINT);
    }
    public function getNavbarJsonPrettyWithSpaces(): string
    {
        return json_encode($this->getNavbarArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    public function getNavbarJsonPrettyWithSpacesAndIndentation(): string
    {
        return json_encode($this->getNavbarArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
