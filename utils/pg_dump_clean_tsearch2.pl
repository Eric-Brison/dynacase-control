#!/usr/bin/perl -w

#
# @author Anakeen
# @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
#

use strict;
use warnings;
use Encode;
use Getopt::Long;

select STDERR; $|=1;
select STDOUT; $|=1;

my @to_remove;
while(<DATA>) {
  chomp;
  push @to_remove, $_;
}

my $opt_schema_mode = 0;
my $opt_load_subst = undef;
my $result = GetOptions(
			'schema' => \$opt_schema_mode,
			'load-subst=s' => \$opt_load_subst,
			);

our %subst;
if( defined $opt_load_subst ) {
  require $opt_load_subst if( -r $opt_load_subst );
}

if( $opt_schema_mode ) {

  # -- Schema conversion mode --

  my $dump;
  my $line;
  while( $line = <STDIN> ) {

    $line = cp1252_to_utf8($line);

    $dump .= $line;
  }

  $dump =~ s/client_encoding = 'LATIN1'/client_encoding = 'UTF8'/;

  $dump =~ s/^(CREATE TABLE style .*?description) character varying\(\d+\)/${1} text/gms;

  my $elmt;
  foreach $elmt (@to_remove) {
    $dump =~ s/^--\n-- \b\Q$elmt\E\b.*?\n--\n.*?\n--/--/gms;
  }

  print $dump;

} else {

  # -- Data conversion mode --

  my $re;
  my @to_remove_regex;
  foreach $re (@to_remove) {
    push @to_remove_regex, qr/^-- \Q$re\E\b/;
  }

  my $remove = 0;
  my $done_encoding = 0;
  my $line;
  while( $line = <STDIN> ) {

    foreach (keys %subst) {
      $line =~ s/\Q$_\E/$subst{$_}/g;
    }

    $line = cp1252_to_utf8($line);

    $done_encoding = 1 if( $done_encoding == 0 && $line =~ s/client_encoding = 'LATIN1'/client_encoding = 'UTF8'/ );

    if( $line =~ m/^-- (?:Data for)? Name: / ) {
      $remove = 0;
      foreach $re (@to_remove_regex) {
	if( $line =~ m/$re/ ) {
	  $remove = 1;
	  last;
	}
      }
    }

    if( $remove == 0 ) {
      print STDOUT $line;
    }
  }

}

exit( 0 );

sub cp1252_to_utf8 {
  my $str = shift;

  foreach (keys %subst) {
    $str =~ s/\Q$_\E/$subst{$_}/g;
  }

  my ($str_dec, $str_enc, $err_pos, $err_byte, $err_msg, $xxd_before, $xxd_after);

  # -- Decode from cp1252 to internal perl format
  my $str_orig = $str;
  my $buff = $str;
  eval { $str_dec = Encode::decode('cp1252', $buff, Encode::FB_QUIET); };
  if( length($buff) > 0 ) {
    # -- log error
    $err_pos = length($str_orig) - length($buff);
    $err_byte = ord(substr($str_orig, $err_pos, 1));
    $xxd_before = join('', map { sprintf("<0x%x>", ord($_)) } split("", substr($str_orig, ($err_pos>3)?($err_pos-3):0, ($err_pos>3)?3:$err_pos)));
    $xxd_after = join('', map { sprintf("<0x%x>", ord($_)) } split("", substr($str_orig, $err_pos, 3)));
    $err_msg = sprintf("Decode error at line %d char %d (0x%x)", $., $err_pos, $err_byte);
    chomp($str = $str_orig);
    $str =~ s/\t/ /g;
    print STDERR sprintf("%s: [%s]\n", $err_msg, $str);
    print STDERR sprintf("%s  %s^ (context %s ^ %s)\n", ' 'x(length($err_msg)), '-'x($err_pos), $xxd_before, $xxd_after);
    # -- force decode
    $str_dec = Encode::decode('cp1252', $str_orig);
  }
  $str = $str_dec;

  # -- Encode from internal perl format to utf8
  $str_orig = $str;
  eval { $str_enc = Encode::encode('utf8', $str, Encode::FB_CROAK); };
  if( $@ ) {
    # -- log error
    chomp($str = $str_orig);
    $str =~ s/\t//g;
    print STDERR sprintf("Encode error at line %s : [%s]\n", $., $str);
    # -- force encode
    $str_enc = Encode::encode('utf8', $str_orig);
  }
  $str = $str_enc;

  return $str;
}

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
