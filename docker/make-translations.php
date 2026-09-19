<?php
/**
 * Extract gettext strings from a theme/plugin tree and generate .pot/.po/.mo.
 *
 * Runs inside the WordPress container (has PHP + wp-includes/pomo). Uses
 * token_get_all() so it is not fooled by strings in comments or concatenation.
 *
 * Usage:
 *   php make-translations.php <src-dir> <domain> <out-dir> <locale> [translations.php]
 *
 * The optional translations.php returns array( 'Persian msgid' => 'English' ).
 * When omitted, msgstr falls back to the msgid (identity passthrough), which is
 * what the default fa_IR catalogue uses so the Persian site is byte-identical.
 */

// Bootstrap WordPress so pomo's dependencies (compat.php's array_last(), etc.)
// are available, then pull in the PO/MO compiler classes.
require '/var/www/html/wp-load.php';
require_once ABSPATH . WPINC . '/pomo/po.php';
require_once ABSPATH . WPINC . '/pomo/mo.php';

$src      = rtrim( $argv[1], '/' );
$domain   = $argv[2];
$out      = rtrim( $argv[3], '/' );
$locale   = $argv[4];
$map_file = isset( $argv[5] ) ? $argv[5] : '';

// Functions whose LAST string argument is the text domain.
$simple = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
// Functions with a context argument before the domain.
$with_context = array( '_x', '_ex', 'esc_html_x', 'esc_attr_x' );

/**
 * Collect msgid => array( contexts ) from one PHP file.
 */
function asc_extract_file( $file, $domain ) {
    global $simple, $with_context;
    $code   = file_get_contents( $file );
    $tokens = token_get_all( $code );
    $found  = array();

    $n = count( $tokens );
    for ( $i = 0; $i < $n; $i++ ) {
        $t = $tokens[ $i ];
        if ( ! is_array( $t ) || T_STRING !== $t[0] ) {
            continue;
        }
        $fn = $t[1];
        $is_simple  = in_array( $fn, $simple, true );
        $is_context = in_array( $fn, $with_context, true );
        if ( ! $is_simple && ! $is_context ) {
            continue;
        }

        // Gather the literal string arguments of this call.
        $args = array();
        $depth = 0;
        $j = $i + 1;
        for ( ; $j < $n; $j++ ) {
            $tk = $tokens[ $j ];
            if ( '(' === $tk ) { $depth++; continue; }
            if ( ')' === $tk ) { $depth--; if ( $depth <= 0 ) { break; } continue; }
            if ( is_array( $tk ) && T_CONSTANT_ENCAPSED_STRING === $tk[0] ) {
                $args[] = asc_unquote( $tk[1] );
            }
        }

        // Expected: [text, domain] or [text, context, domain].
        $last = empty( $args ) ? '' : $args[ count( $args ) - 1 ];
        if ( $domain !== $last ) {
            continue;
        }
        $msgid = $args[0];
        if ( '' === trim( $msgid ) ) {
            continue;
        }
        if ( ! isset( $found[ $msgid ] ) ) {
            $found[ $msgid ] = array();
        }
        if ( $is_context && isset( $args[1] ) ) {
            $found[ $msgid ][] = $args[1];
        }
    }
    return $found;
}

function asc_unquote( $s ) {
    $q = $s[0];
    $body = substr( $s, 1, -1 );
    if ( "'" === $q ) {
        return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
    }
    // Double-quoted: handle the common escapes.
    return stripcslashes( $body );
}

// Walk the source tree.
$all = array();
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
foreach ( $rii as $f ) {
    if ( 'php' !== strtolower( $f->getExtension() ) ) {
        continue;
    }
    foreach ( asc_extract_file( $f->getPathname(), $domain ) as $msgid => $ctxs ) {
        if ( ! isset( $all[ $msgid ] ) ) {
            $all[ $msgid ] = array();
        }
        $all[ $msgid ] = array_values( array_unique( array_merge( $all[ $msgid ], $ctxs ) ) );
    }
}
ksort( $all, SORT_STRING );

// Optional translations map.
$map = array();
if ( $map_file && is_file( $map_file ) ) {
    $loaded = include $map_file;
    if ( is_array( $loaded ) ) {
        $map = $loaded;
    }
}

$is_pot = ( '' === $locale );
$plural = 'nplurals=1; plural=0;';
if ( 'en_US' === $locale ) {
    $plural = 'nplurals=2; plural=(n != 1);';
}

$po = new PO();
$po->set_header( 'Project-Id-Version', 'lylyrose' );
$po->set_header( 'MIME-Version', '1.0' );
$po->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
$po->set_header( 'Content-Transfer-Encoding', '8bit' );
$po->set_header( 'Language', $is_pot ? '' : $locale );
$po->set_header( 'Plural-Forms', $plural );
$po->set_header( 'X-Generator', 'asc make-translations.php' );

foreach ( $all as $msgid => $ctxs ) {
    $entry = new Translation_Entry( array(
        'singular'   => $msgid,
        'context'    => empty( $ctxs ) ? null : $ctxs[0],
    ) );
    if ( ! $is_pot ) {
        $entry->translations = array( isset( $map[ $msgid ] ) ? $map[ $msgid ] : $msgid );
    }
    $po->add_entry( $entry );
}

if ( ! is_dir( $out ) ) {
    mkdir( $out, 0755, true );
}

$base = $is_pot ? "$domain.pot" : "$domain-$locale.po";
$po->export_to_file( "$out/$base" );

$mo_path = '';
if ( ! $is_pot ) {
    $mo = new MO();
    $mo->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
    $mo->set_header( 'Plural-Forms', $plural );
    foreach ( $all as $msgid => $ctxs ) {
        $mo->add_entry( new Translation_Entry( array(
            'singular'     => $msgid,
            'context'      => empty( $ctxs ) ? null : $ctxs[0],
            'translations' => array( isset( $map[ $msgid ] ) ? $map[ $msgid ] : $msgid ),
        ) ) );
    }
    $mo_path = "$out/$domain-$locale.mo";
    $mo->export_to_file( $mo_path );

    // Round-trip sanity check.
    $check = new MO();
    $check->import_from_file( $mo_path );
    $entries = count( $check->entries );
} else {
    $entries = count( $all );
}

echo "domain=$domain locale=" . ( $is_pot ? '(pot)' : $locale )
    . " strings=$entries file=$out/$base" . ( $mo_path ? " + " . basename( $mo_path ) : '' )
    . ( $mo_path ? " verify_entries=$entries" : '' ) . "\n";
