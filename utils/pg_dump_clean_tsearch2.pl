#!/usr/bin/perl -w

use strict;
use warnings;

if( not open(ICONV, '-|', 'iconv -c -f WINDOWS-1252 -t UTF-8') ) {
  print STDERR "Error running iconv: $!\n";
  exit( 1 );
}

my $dump;
{
  local $/;
  $dump = <ICONV>;
}

my @to_remove;
while(<DATA>) {
  chomp;
  push @to_remove, $_;
}

$dump =~ s/^client_encoding = 'LATIN1'/client_encoding = 'UTF8'/;

$dump =~ s/^(CREATE TABLE style .*?description) character varying\(\d+\)/${1} text/gms;

my $elmt;
foreach $elmt (@to_remove) {
  $dump =~ s/^--\n-- \b\Q$elmt\E\b.*?\n--\n.*?\n--/--/gms;
}

print $dump;

__DATA__
Name: gtsq; Type: SHELL TYPE
Name: gtsq_in(cstring); Type: FUNCTION
Name: gtsq_out(gtsq); Type: FUNCTION
Name: gtsq; Type: TYPE
Name: gtsvector; Type: SHELL TYPE
Name: gtsvector_in(cstring); Type: FUNCTION
Name: gtsvector_out(gtsvector); Type: FUNCTION
Name: gtsvector; Type: TYPE
Name: tsquery; Type: SHELL TYPE
Name: tsquery_in(cstring); Type: FUNCTION
Name: tsquery_out(tsquery); Type: FUNCTION
Name: tsquery; Type: TYPE
Name: tsvector; Type: SHELL TYPE
Name: tsvector_in(cstring); Type: FUNCTION
Name: tsvector_out(tsvector); Type: FUNCTION
Name: tsvector; Type: TYPE
Name: concat(tsvector, tsvector); Type: FUNCTION
Name: dex_init(internal); Type: FUNCTION
Name: dex_lexize(internal, internal, integer); Type: FUNCTION
Name: exectsq(tsvector, tsquery); Type: FUNCTION
Name: FUNCTION exectsq(tsvector, tsquery); Type: COMMENT
Name: get_covers(tsvector, tsquery); Type: FUNCTION
Name: gin_extract_tsquery(tsquery, internal, internal); Type: FUNCTION
Name: gin_extract_tsvector(tsvector, internal); Type: FUNCTION
Name: gin_ts_consistent(internal, internal, tsquery); Type: FUNCTION
Name: gtsq_compress(internal); Type: FUNCTION
Name: gtsq_consistent(gtsq, internal, integer); Type: FUNCTION
Name: gtsq_decompress(internal); Type: FUNCTION
Name: gtsq_penalty(internal, internal, internal); Type: FUNCTION
Name: gtsq_picksplit(internal, internal); Type: FUNCTION
Name: gtsq_same(gtsq, gtsq, internal); Type: FUNCTION
Name: gtsq_union(bytea, internal); Type: FUNCTION
Name: gtsvector_compress(internal); Type: FUNCTION
Name: gtsvector_consistent(gtsvector, internal, integer); Type: FUNCTION
Name: gtsvector_decompress(internal); Type: FUNCTION
Name: gtsvector_penalty(internal, internal, internal); Type: FUNCTION
Name: gtsvector_picksplit(internal, internal); Type: FUNCTION
Name: gtsvector_same(gtsvector, gtsvector, internal); Type: FUNCTION
Name: gtsvector_union(internal, internal); Type: FUNCTION
Name: headline(oid, text, tsquery, text); Type: FUNCTION
Name: headline(oid, text, tsquery); Type: FUNCTION
Name: headline(text, text, tsquery, text); Type: FUNCTION
Name: headline(text, text, tsquery); Type: FUNCTION
Name: headline(text, tsquery, text); Type: FUNCTION
Name: headline(text, tsquery); Type: FUNCTION
Name: length(tsvector); Type: FUNCTION
Name: lexize(oid, text); Type: FUNCTION
Name: lexize(text, text); Type: FUNCTION
Name: lexize(text); Type: FUNCTION
Name: numnode(tsquery); Type: FUNCTION
Name: parse(oid, text); Type: FUNCTION
Name: parse(text, text); Type: FUNCTION
Name: parse(text); Type: FUNCTION
Name: plainto_tsquery(oid, text); Type: FUNCTION
Name: plainto_tsquery(text, text); Type: FUNCTION
Name: plainto_tsquery(text); Type: FUNCTION
Name: prsd_end(internal); Type: FUNCTION
Name: prsd_getlexeme(internal, internal, internal); Type: FUNCTION
Name: prsd_headline(internal, internal, internal); Type: FUNCTION
Name: prsd_lextype(internal); Type: FUNCTION
Name: prsd_start(internal, integer); Type: FUNCTION
Name: querytree(tsquery); Type: FUNCTION
Name: rank(real[], tsvector, tsquery); Type: FUNCTION
Name: rank(real[], tsvector, tsquery, integer); Type: FUNCTION
Name: rank(tsvector, tsquery); Type: FUNCTION
Name: rank(tsvector, tsquery, integer); Type: FUNCTION
Name: rank_cd(real[], tsvector, tsquery); Type: FUNCTION
Name: rank_cd(real[], tsvector, tsquery, integer); Type: FUNCTION
Name: rank_cd(tsvector, tsquery); Type: FUNCTION
Name: rank_cd(tsvector, tsquery, integer); Type: FUNCTION
Name: rank_cd(integer, tsvector, tsquery); Type: FUNCTION
Name: rank_cd(integer, tsvector, tsquery, integer); Type: FUNCTION
Name: reset_tsearch(); Type: FUNCTION
Name: rewrite(tsquery, text); Type: FUNCTION
Name: rewrite(tsquery, tsquery, tsquery); Type: FUNCTION
Name: rewrite_accum(tsquery, tsquery[]); Type: FUNCTION
Name: rewrite_finish(tsquery); Type: FUNCTION
Name: rexectsq(tsquery, tsvector); Type: FUNCTION
Name: FUNCTION rexectsq(tsquery, tsvector); Type: COMMENT
Name: set_curcfg(integer); Type: FUNCTION
Name: set_curcfg(text); Type: FUNCTION
Name: set_curdict(integer); Type: FUNCTION
Name: set_curdict(text); Type: FUNCTION
Name: set_curprs(integer); Type: FUNCTION
Name: set_curprs(text); Type: FUNCTION
Name: setweight(tsvector, "char"); Type: FUNCTION
Name: setweight2(text, "char"); Type: FUNCTION
Name: setweight2(text); Type: FUNCTION
Name: show_curcfg(); Type: FUNCTION
Name: snb_en_init(internal); Type: FUNCTION
Name: snb_lexize(internal, internal, integer); Type: FUNCTION
Name: snb_ru_init(internal); Type: FUNCTION
Name: snb_ru_init_koi8(internal); Type: FUNCTION
Name: snb_ru_init_utf8(internal); Type: FUNCTION
Name: spell_init(internal); Type: FUNCTION
Name: spell_lexize(internal, internal, integer); Type: FUNCTION
Name: stat(text); Type: FUNCTION
Name: stat(text, text); Type: FUNCTION
Name: strip(tsvector); Type: FUNCTION
Name: syn_init(internal); Type: FUNCTION
Name: syn_lexize(internal, internal, integer); Type: FUNCTION
Name: thesaurus_init(internal); Type: FUNCTION
Name: thesaurus_lexize(internal, internal, integer, internal); Type: FUNCTION
Name: to_tsquery(oid, text); Type: FUNCTION
Name: to_tsquery(text, text); Type: FUNCTION
Name: to_tsquery(text); Type: FUNCTION
Name: to_tsvector(oid, text); Type: FUNCTION
Name: to_tsvector(text, text); Type: FUNCTION
Name: to_tsvector(text); Type: FUNCTION
Name: token_type(integer); Type: FUNCTION
Name: token_type(text); Type: FUNCTION
Name: token_type(); Type: FUNCTION
Name: tsearch2(); Type: FUNCTION
Name: tsq_mcontained(tsquery, tsquery); Type: FUNCTION
Name: tsq_mcontains(tsquery, tsquery); Type: FUNCTION
Name: tsquery_and(tsquery, tsquery); Type: FUNCTION
Name: tsquery_cmp(tsquery, tsquery); Type: FUNCTION
Name: tsquery_eq(tsquery, tsquery); Type: FUNCTION
Name: tsquery_ge(tsquery, tsquery); Type: FUNCTION
Name: tsquery_gt(tsquery, tsquery); Type: FUNCTION
Name: tsquery_le(tsquery, tsquery); Type: FUNCTION
Name: tsquery_lt(tsquery, tsquery); Type: FUNCTION
Name: tsquery_ne(tsquery, tsquery); Type: FUNCTION
Name: tsquery_not(tsquery); Type: FUNCTION
Name: tsquery_or(tsquery, tsquery); Type: FUNCTION
Name: tsvector_cmp(tsvector, tsvector); Type: FUNCTION
Name: tsvector_eq(tsvector, tsvector); Type: FUNCTION
Name: tsvector_ge(tsvector, tsvector); Type: FUNCTION
Name: tsvector_gt(tsvector, tsvector); Type: FUNCTION
Name: tsvector_le(tsvector, tsvector); Type: FUNCTION
Name: tsvector_lt(tsvector, tsvector); Type: FUNCTION
Name: tsvector_ne(tsvector, tsvector); Type: FUNCTION
Name: rewrite(tsquery[]); Type: AGGREGATE
Name: !!; Type: OPERATOR
Name: &&; Type: OPERATOR
Name: <; Type: OPERATOR
Name: <; Type: OPERATOR
Name: <=; Type: OPERATOR
Name: <=; Type: OPERATOR
Name: <>; Type: OPERATOR
Name: <>; Type: OPERATOR
Name: <@; Type: OPERATOR
Name: =; Type: OPERATOR
Name: =; Type: OPERATOR
Name: >; Type: OPERATOR
Name: >; Type: OPERATOR
Name: >=; Type: OPERATOR
Name: >=; Type: OPERATOR
Name: @; Type: OPERATOR
Name: @>; Type: OPERATOR
Name: @@; Type: OPERATOR
Name: @@; Type: OPERATOR
Name: @@@; Type: OPERATOR
Name: @@@; Type: OPERATOR
Name: ||; Type: OPERATOR
Name: ||; Type: OPERATOR
Name: ~; Type: OPERATOR
Name: gin_tsvector_ops; Type: OPERATOR CLASS
Name: gist_tp_tsquery_ops; Type: OPERATOR CLASS
Name: gist_tsvector_ops; Type: OPERATOR CLASS
Name: tsquery_ops; Type: OPERATOR CLASS
Name: tsvector_ops; Type: OPERATOR CLASS
Data for Name: pg_ts_dict; Type: TABLE DATA
Data for Name: pg_ts_parser; Type: TABLE DATA
