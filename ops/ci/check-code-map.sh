#!/usr/bin/env bash

# Validates the "Peta kode" section of every module README (docs/standards/documentation-standard.md):
# each backticked path or glob in its table must still match at least one file or directory, and
# every alternative of a `{a,b}` group must match on its own. A stale code map makes agents and
# developers read the wrong files, so it fails CI like a broken link.

set -Eeuo pipefail

readonly repository_root="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"

cd "$repository_root"

readmes=()
while IFS= read -r -d '' file; do
    readmes+=("$file")
done < <(find docs/products -mindepth 2 -maxdepth 2 -type f -name README.md -print0 2>/dev/null | sort -z)

if (( ${#readmes[@]} == 0 )); then
    echo 'No module README found under docs/products.' >&2
    exit 66
fi

perl -e '
use strict;
use warnings;
use File::Glob qw(bsd_glob);

my (@errors, $sections, $entries);

for my $file (@ARGV) {
    open my $fh, "<:encoding(UTF-8)", $file or die "$file: $!";
    my $in_map = 0;
    my $found = 0;
    while (my $line = <$fh>) {
        if ($line =~ /^## /) {
            $in_map = $line =~ /^## Peta kode\s*$/ ? 1 : 0;
            if ($in_map) { $found = 1; $sections++; }
            next;
        }
        next unless $in_map && $line =~ /^\|/ && $line !~ /^\|\s*-/ && $line !~ /^\|\s*Lapisan\s*\|/;
        my (undef, undef, $locations) = split /\|/, $line, 4;
        next unless defined $locations;
        while ($locations =~ /`([^`]+)`/g) {
            my $pattern = $1;
            $entries++;
            my @alternatives = $pattern =~ /\{([^}]+)\}/
                ? map { (my $p = $pattern) =~ s/\{[^}]+\}/$_/; $p } split /,/, $1
                : ($pattern);
            for my $alternative (@alternatives) {
                (my $glob = $alternative) =~ s{/$}{};
                my @matches = grep { -e $_ } bsd_glob($glob);
                @matches = grep { -d $_ } @matches if $alternative =~ m{/$};
                push @errors, "$file: \"$alternative\" matches nothing" unless @matches;
            }
        }
    }
    push @errors, "$file: missing \"## Peta kode\" section" unless $found;
}

if (@errors) {
    print STDERR "$_\n" for @errors;
    printf STDERR "Code map check failed: %d problem(s).\n", scalar @errors;
    exit 1;
}

printf "Code map check passed: %d section(s), %d path pattern(s).\n", $sections, $entries;
' "${readmes[@]}"
