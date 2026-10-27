#!/usr/bin/env bash

# Validates internal documentation links (docs/standards/documentation-standard.md §8):
#   1. relative Markdown links in docs/**/*.md, README.md, and ops/**/README.md resolve to an
#      existing file, and a #anchor to another Markdown file matches one of its headings;
#   2. every `docs/<path>.md|csv` mentioned in tracked source, tests, config, ops, or docs exists.
# Templates under docs/templates/ link to the names files get after copying, so their relative
# links are skipped. Lines containing "Path lama" record pre-move paths on purpose. Paths in
# ops/ci/doc-links-allowlist.txt are known references to files outside this repository, and the
# checker's own test fixtures in ops/ci/tests/ are not scanned.

set -Eeuo pipefail

readonly repository_root="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
readonly allowlist_file="${repository_root}/ops/ci/doc-links-allowlist.txt"

cd "$repository_root"

export DOC_LINKS_ALLOWLIST="$allowlist_file"

markdown_files=()
while IFS= read -r -d '' file; do
    markdown_files+=("$file")
done < <(find docs README.md ops -type f -name '*.md' -not -path 'docs/templates/*' -not -path 'ops/ci/tests/*' -print0 2>/dev/null | sort -z)

reference_files=()
while IFS= read -r -d '' file; do
    reference_files+=("$file")
done < <(find app config database resources routes tests ops docs .github README.md -type f \
    \( -name '*.php' -o -name '*.md' -o -name '*.yml' -o -name '*.yaml' -o -name '*.sh' -o -name '*.json' \) \
    -not -path 'docs/templates/*' -not -path 'ops/ci/tests/*' -print0 2>/dev/null | sort -z)

perl -e '
use strict;
use warnings;
use File::Basename qw(dirname);
use File::Spec;

my %allowed;
if (open my $fh, "<", $ENV{DOC_LINKS_ALLOWLIST}) {
    while (<$fh>) { chomp; s/#.*//; s/^\s+|\s+$//g; $allowed{$_} = 1 if length; }
}

my ($markdown_count, @rest) = @ARGV;
my @markdown = @rest[0 .. $markdown_count - 1];
my @references = @rest[$markdown_count .. $#rest];

my @errors;
my %anchor_cache;

sub slug {
    my ($text) = @_;
    $text = lc $text;
    $text =~ s/`//g;
    $text =~ s/[^\p{L}\p{N}\s_-]//g;
    $text =~ s/\s/-/g;
    return $text;
}

sub anchors_for {
    my ($path) = @_;
    return $anchor_cache{$path} if $anchor_cache{$path};
    my %seen;
    my %anchors;
    open my $fh, "<:encoding(UTF-8)", $path or return {};
    my $in_fence = 0;
    while (my $line = <$fh>) {
        $in_fence = !$in_fence if $line =~ /^\s*```/;
        next if $in_fence;
        next unless $line =~ /^#{1,6}\s+(.*?)\s*#*\s*$/;
        my $base = slug($1);
        my $anchor = exists $seen{$base} ? "$base-" . (++$seen{$base}) : $base;
        $seen{$base} //= 0;
        $anchors{$anchor} = 1;
    }
    return $anchor_cache{$path} = \%anchors;
}

for my $file (@markdown) {
    open my $fh, "<:encoding(UTF-8)", $file or next;
    my $in_fence = 0;
    while (my $line = <$fh>) {
        my $line_number = $.;
        $in_fence = !$in_fence if $line =~ /^\s*```/;
        next if $in_fence;
        (my $prose = $line) =~ s/`[^`]*`//g;
        while ($prose =~ /\]\(([^)\s]+)\)/g) {
            my $target = $1;
            next if $target =~ m{^(?:[a-z]+:|//)}i;
            my ($path, $anchor) = split /#/, $target, 2;
            my $resolved = length $path
                ? File::Spec->canonpath(File::Spec->catfile(dirname($file), $path))
                : $file;
            if (length $path && !-e $resolved) {
                push @errors, "$file:$line_number: broken link -> $target";
                next;
            }
            if (defined $anchor && length $anchor && $resolved =~ /\.md$/ && !anchors_for($resolved)->{$anchor}) {
                push @errors, "$file:$line_number: missing anchor -> $target";
            }
        }
    }
}

for my $file (@references) {
    open my $fh, "<:encoding(UTF-8)", $file or next;
    while (my $line = <$fh>) {
        next if $line =~ /Path lama/;
        while ($line =~ m{(docs/[A-Za-z0-9_./-]+\.(?:md|csv))}g) {
            my $path = $1;
            next if $allowed{$path} || -e $path;
            push @errors, "$file:$.: missing document -> $path";
        }
    }
}

if (@errors) {
    print STDERR "$_\n" for @errors;
    printf STDERR "Documentation link check failed: %d problem(s).\n", scalar @errors;
    exit 1;
}

printf "Documentation link check passed: %d Markdown file(s), %d reference file(s).\n", scalar @markdown, scalar @references;
' "${#markdown_files[@]}" "${markdown_files[@]}" "${reference_files[@]}"
