<?php
// Run inside the WordPress container. Dry-run by default; pass --write to store.
// Usage: php seed-needs-data.php [--write]
require '/var/www/html/wp-load.php';

$write = in_array( '--write', $argv, true );

$usage = array(
	'face_wash' => 'صبح و شب، روی صورت خیس بمالید و با آب بشویید.',
	'foam'      => 'صبح و شب، روی صورت خیس بمالید و با آب بشویید.',
	'micellar'  => 'با پنبه روی صورت بکشید؛ نیازی به آبکشی نیست.',
	'toner'     => 'بعد از شستشو، با پنبه روی صورت بکشید.',
	'scrub'     => 'هفته‌ای ۱ تا ۲ بار روی پوست خیس بمالید و بشویید.',
	'moist'     => 'صبح و شب پس از شستشو، روی پوست بمالید.',
	'sunscreen' => 'هر روز صبح، ۲۰ دقیقه قبل از بیرون رفتن بزنید.',
	'body'      => 'روزانه در حمام یا بعد از دوش استفاده کنید.',
	'shampoo'   => 'موی خیس را بشویید؛ هفته‌ای ۲ تا ۳ بار.',
	'mask'      => 'بعد از شامپو، ۳ تا ۵ دقیقه بمالید و بشویید.',
	'leave_in'  => 'روی موی نمدار بزنید؛ نیازی به آبکشی نیست.',
	'oil'       => 'چند قطره روی موی خشک یا نمدار بمالید.',
);

// id => [skin/hair types, concerns, usage key]
$map = array(
	4716 => array( array( 'normal', 'combination', 'oily' ), array( 'dullness' ), 'scrub' ),
	4748 => array( array( 'dry', 'normal', 'sensitive' ), array( 'dehydration' ), 'moist' ),
	4718 => array( array( 'sensitive', 'dry' ), array( 'redness' ), 'body' ),
	4750 => array( array( 'normal', 'dry', 'combination' ), array( 'dehydration', 'dullness' ), 'moist' ),
	4720 => array( array( 'dry', 'normal' ), array( 'dehydration' ), 'body' ),
	4752 => array( array( 'normal', 'dry', 'oily', 'combination', 'sensitive' ), array( 'aging' ), 'sunscreen' ),
	4722 => array( array( 'sensitive', 'dry' ), array( 'redness', 'dehydration' ), 'body' ),
	4724 => array( array( 'dry', 'normal' ), array( 'dehydration', 'dullness' ), 'moist' ),
	4726 => array( array( 'dry', 'normal' ), array( 'dehydration' ), 'moist' ),
	4728 => array( array( 'sensitive', 'dry' ), array( 'redness', 'dehydration' ), 'moist' ),
	4730 => array( array( 'normal', 'dry' ), array( 'aging' ), 'moist' ),
	4732 => array( array( 'oily', 'combination' ), array( 'acne', 'dullness' ), 'scrub' ),
	4734 => array( array( 'oily', 'combination' ), array( 'acne', 'dullness' ), 'scrub' ),
	4736 => array( array( 'oily', 'combination' ), array( 'acne', 'dullness' ), 'scrub' ),
	4738 => array( array( 'normal', 'combination' ), array( 'dullness' ), 'scrub' ),
	4740 => array( array( 'normal', 'dry' ), array( 'aging', 'dehydration' ), 'moist' ),
	4710 => array( array( 'sensitive' ), array( 'redness' ), 'foam' ),
	4742 => array( array( 'dry', 'normal' ), array( 'dehydration' ), 'moist' ),
	4712 => array( array( 'normal', 'dry', 'combination' ), array( 'dehydration', 'dullness' ), 'foam' ),
	4744 => array( array( 'normal', 'dry' ), array( 'dehydration', 'dullness' ), 'moist' ),
	4714 => array( array( 'normal', 'oily' ), array( 'dullness' ), 'scrub' ),
	4746 => array( array( 'normal', 'dry' ), array( 'aging' ), 'moist' ),
	4684 => array( array( 'oily' ), array( 'acne' ), 'face_wash' ),
	4686 => array( array( 'normal', 'dry' ), array( 'aging' ), 'face_wash' ),
	4688 => array( array( 'dry' ), array( 'dehydration' ), 'face_wash' ),
	4690 => array( array( 'sensitive' ), array( 'redness', 'dehydration' ), 'face_wash' ),
	4692 => array( array( 'normal', 'dry', 'combination' ), array( 'aging' ), 'micellar' ),
	4694 => array( array( 'normal' ), array( 'aging' ), 'micellar' ),
	4696 => array( array( 'dry' ), array( 'dehydration' ), 'micellar' ),
	4698 => array( array( 'sensitive' ), array( 'redness', 'dehydration' ), 'micellar' ),
	4700 => array( array( 'oily' ), array( 'acne' ), 'toner' ),
	4702 => array( array( 'dry', 'normal' ), array( 'dullness', 'dark_spots' ), 'toner' ),
	4704 => array( array( 'normal', 'combination' ), array( 'dullness', 'dark_spots' ), 'toner' ),
	4706 => array( array( 'oily' ), array( 'acne' ), 'foam' ),
	4708 => array( array( 'dry' ), array( 'dehydration' ), 'foam' ),
	4779 => array( array( 'straight', 'wavy', 'curly' ), array( 'frizz', 'damage' ), 'leave_in' ),
	4781 => array( array( 'wavy', 'curly', 'straight' ), array( 'frizz', 'dryness' ), 'leave_in' ),
	4783 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'dryness', 'damage' ), 'oil' ),
	4753 => array( array( 'curly', 'coily', 'wavy' ), array( 'dryness', 'frizz' ), 'shampoo' ),
	4759 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'mask' ),
	4761 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'shampoo' ),
	4767 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'mask' ),
	4769 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'shampoo' ),
	4775 => array( array( 'straight', 'wavy', 'curly' ), array( 'damage', 'dryness' ), 'mask' ),
	4777 => array( array( 'straight', 'wavy', 'curly' ), array( 'dryness', 'damage' ), 'shampoo' ),
	4755 => array( array( 'curly', 'coily', 'wavy' ), array( 'frizz', 'dryness' ), 'leave_in' ),
	4757 => array( array( 'curly', 'coily', 'wavy' ), array( 'frizz', 'dryness' ), 'leave_in' ),
	4763 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'leave_in' ),
	4765 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'dryness' ), 'leave_in' ),
	4771 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'leave_in' ),
	4773 => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'leave_in' ),
);

$changes = 0;
foreach ( $map as $id => $row ) {
	$post = get_post( $id );
	if ( ! $post || 'product' !== $post->post_type ) {
		echo "SKIP $id: not a product\n";
		continue;
	}
	list( $types, $concerns, $key ) = $row;
	$text = $usage[ $key ];
	printf( "%d | %s | types=%s | concerns=%s\n   %s\n", $id, $post->post_title, implode( ',', $types ), implode( ',', $concerns ), $text );
	if ( $write ) {
		update_post_meta( $id, '_needs_types', $types );
		update_post_meta( $id, '_needs_concerns', $concerns );
		update_post_meta( $id, '_needs_usage', $text );
		$changes++;
	}
}
echo $write ? "WROTE $changes products\n" : "DRY RUN: nothing written (" . count( $map ) . " products previewed)\n";
