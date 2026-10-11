<?php
/**
 * Shipped colour presets for the Lyly Rose store. Data only: the mechanism lives in
 * ASC_Palette and reads these through the asc_palette_presets filter.
*/

return array(
	// Lyly Rose — the brand default (agreed 2026-10-10). Deep teal is the
	// only strong colour: CTAs, footer and the announce bar. Everything
	// else is white space and pastel mint/blush.
	// Two values differ from the brief, both for WCAG AA: --dk-muted is
	// #737373 not #8A8A8A (3.45:1 on white), and the brief's Soft Pink /
	// Blush have no token to land in, so they stay available to CSS as
	// literals rather than becoming two near-identical surfaces.
	'lylyrose' => array(
		'label'     => 'لی‌لی رز',
		'red'       => '#064048',
		'teal'      => '#0a5a63',
		'ink'       => '#1a1a1a',
		'text'      => '#5a5a5a',
		'body-text' => '#1a1a1a',
		'muted'     => '#737373',
		'bg'        => '#ffffff',
		'border'    => '#e8eeee',
		'white'     => '#fafafa',
		'badge-red' => '#064048',
		'bar'       => '#064048',
		'on-bar'    => '#ffffff',
		// Derived steps. --dk-red-light is the tinted panel behind hovered
		// links and the demo banner, so it takes the brief's Soft Mint.
		'red-dark'  => '#052e34',
		'red-light' => '#ebf6f5',
		'on-accent' => '#ffffff',
		'on-gold'   => '#ffffff',
		'on-ink'    => '#ffffff',
		'star'      => '#96610d',
		'green'     => '#0f7a3e',
		'orange'    => '#a06a00',
	),
	'ivory' => array(
		'label' => 'عاجی',
		'red'       => '#9c5c5f',
		'teal'      => '#8a6848',
		'ink'       => '#302522',
		'text'      => '#5a4f49',
		'muted'     => '#6e5f58',
		'bg'        => '#f8f3ee',
		'border'    => '#e5dcd4',
		'white'     => '#ffffff',
		'badge-red' => '#b5484d',
		// Matches :root, so the light palettes keep this strip exactly as it
		// renders today. Only Night needs a different value.
		'bar'       => '#302522',
		'on-bar'    => '#ffffff',
	),
	'navy'  => array(
		'label' => 'سرمه‌ای',
		'red'       => '#070f27',
		'teal'      => '#5a6b85',
		'ink'       => '#070f27',
		'text'      => '#4a5468',
		'muted'     => '#5c6675',
		'bg'        => '#eaecef',
		'border'    => '#d5dae3',
		'white'     => '#fefefe',
		'badge-red' => '#c0392b',
		'bar'       => '#070f27',
		'on-bar'    => '#ffffff',
	),
	// Derived by docker/contrast-check.py --derive-night, not hand-picked:
	// a dark surface needs a LIGHT accent, and the label on that accent has
	// to be dark. Verified at 7.24:1 and 8.16:1 for the button label.
	'night' => array(
		'label' => 'شب',
		'red'       => '#789df9',
		'teal'      => '#c4a577',
		'ink'       => '#f2f4f8',
		'text'      => '#bec4d1',
		'body-text' => '#d5d9e2',
		'muted'     => '#949daf',
		'bg'        => '#12151c',
		'border'    => '#323945',
		// --dk-white is the card surface, not a constant. Every surface
		// rule reads it, so on a dark page it has to be a lighter dark;
		// #ffffff left a white slab sitting on #12151c.
		'white'     => '#1b1f28',
		'badge-red' => '#f96868',
		'on-accent' => '#0d1016',
		'on-gold'   => '#0d1016',
		'on-ink'    => '#0d1016',
		// Night's --dk-ink is #f2f4f8, which rendered this strip near-white
		// across the dark page. The bar is now the darkest surface in the
		// palette and the label stays light on it: 14.01:1.
		'bar'       => '#08090d',
		'on-bar'    => '#d5d9e2',
		// Derived steps, re-derived for a dark surface. Leaving the
		// light values in would put a #f8f1f0 panel on #12151c.
		'red-dark'  => '#5b7fd4',
		'red-light' => '#1e2534',
		'green'     => '#4fd07f',
		'orange'    => '#e0a03a',
		'star'      => '#e8b64c',
	),
);
