<?php
/*
 * Proves the seeder's --dry-run guard, so a preview can never silently write.
 *
 *   docker exec -i -u www-data lylyrose-wp php docker/dry-run-guard-check.php
 *
 * Reads the guard expression out of the seeder rather than restating it, so
 * this file cannot pass while the seeder still carries the bare-word bug.
 * Exit 0 = every flag form classified correctly. Exit 1 = a form misclassified.
 */
$seeder = __DIR__ . '/seed-finder-data.php';
$src    = file_get_contents( $seeder );
if (preg_match('/preg_grep\(\s*\'([^\']+)\'/', $src, $m)) {
	$re = $m[1];
	$guard = function(array $argv) use ($re) { return count(preg_grep($re, $argv)) > 0; };
	echo "guard in shipped file: preg_grep($re)\n";
} elseif (preg_match('/in_array\(\s*\'([^\']+)\'/', $src, $m)) {
	$word = $m[1];
	$guard = function(array $argv) use ($word) { return in_array($word, $argv, true); };
	echo "guard in shipped file: in_array('$word', argv, true)\n";
} else {
	fwrite(STDERR, "FAIL: no guard expression found in the seeder\n");
	exit(2);
}
echo "\n";

$cases = [
	[['seed'],                    false, 'plain run writes'],
	[['seed','dry-run'],          true,  'bare word (docs form) previews'],
	[['seed','--dry-run'],        true,  'conventional form previews'],
	[['seed','-dry-run'],         true,  'single dash previews'],
	[['seed','--dry-run=false'],  false, 'explicit value writes'],
	[['seed','--dryruntimes'],    false, 'near-miss writes'],
	[['seed','no-dry-run'],       false, 'substring must not match'],
	[['seed','--dry-run','-x'],  true,  'flag not in first position'],
];
$bad = 0;
foreach ($cases as [$argv, $want, $why]) {
	$got = $guard($argv);
	$ok  = $got === $want;
	$bad += $ok ? 0 : 1;
	printf("%-4s %-28s %-6s  %s\n", $ok ? 'ok' : 'BAD', implode(' ', $argv),
		$got ? 'PREVIEW' : 'WRITES', $why);
}
printf("\n%s\n", $bad ? "$bad FAILED" : 'all '.count($cases).' guard cases pass');
exit($bad ? 1 : 0);
