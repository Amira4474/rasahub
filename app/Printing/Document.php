<?php

namespace App\Printing;

/**
 * What to print, line by line, without depending on a printer brand. The
 * print gateway turns it into the printer's own commands; the width in
 * characters comes from the printer's paper.
 *
 * Each line is one of:
 *   text  — {text, align: left|center|right, size: normal|large, bold}
 *   row   — {left, right, bold}: a label on the left, an amount on the right
 *   rule  — a dashed line across the paper
 *   feed  — {lines}: blank lines
 *   cut   — cut the paper
 */
class Document
{
    /** @var list<array<string, mixed>> */
    private array $lines = [];

    public function text(string $text, string $align = 'left', bool $large = false, bool $bold = false): self
    {
        $this->lines[] = ['type' => 'text', 'text' => $text, 'align' => $align, 'size' => $large ? 'large' : 'normal', 'bold' => $bold];

        return $this;
    }

    public function center(string $text, bool $large = false, bool $bold = false): self
    {
        return $this->text($text, 'center', $large, $bold);
    }

    public function row(string $left, string $right, bool $bold = false): self
    {
        $this->lines[] = ['type' => 'row', 'left' => $left, 'right' => $right, 'bold' => $bold];

        return $this;
    }

    public function rule(): self
    {
        $this->lines[] = ['type' => 'rule'];

        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->lines[] = ['type' => 'feed', 'lines' => $lines];

        return $this;
    }

    public function cut(): self
    {
        $this->lines[] = ['type' => 'cut'];

        return $this;
    }

    /**
     * @return array{version: int, lines: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return ['version' => 1, 'lines' => $this->lines];
    }
}
