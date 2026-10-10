<?php
// Run inside the WordPress container. Dry-run by default; pass --write to store.
// Keyed by SKU: product IDs differ between local and the live store.
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
	'STIVES-BW-PEACHJASMINE-EXFOLIATING' => array( array( 'normal', 'combination', 'oily' ), array( 'dullness' ), 'scrub' ),
	'STIVES-MC-ARGANGRAPE-NOURISHING-170' => array( array( 'dry', 'normal', 'sensitive' ), array( 'dehydration' ), 'moist' ),
	'STIVES-BW-ROSEWATERALOE-REFRESHING' => array( array( 'sensitive', 'dry' ), array( 'redness' ), 'body' ),
	'STIVES-MC-ALOEVERA-LIGHTENING-170' => array( array( 'normal', 'dry', 'combination' ), array( 'dehydration', 'dullness' ), 'moist' ),
	'STIVES-BW-COCONUTORCHID-HYDRATING' => array( array( 'dry', 'normal' ), array( 'dehydration' ), 'body' ),
	'STIVES-SPF50-COLLAGENELASTIN' => array( array( 'normal', 'dry', 'oily', 'combination', 'sensitive' ), array( 'aging' ), 'sunscreen' ),
	'STIVES-BW-OATMEALSHEA-SOOTHING' => array( array( 'sensitive', 'dry' ), array( 'redness', 'dehydration' ), 'body' ),
	'STIVES-BL-ROSEARGAN-SMOOTHING' => array( array( 'dry', 'normal' ), array( 'dehydration', 'dullness' ), 'moist' ),
	'STIVES-BL-COCONUTORCHID-SOFTENING' => array( array( 'dry', 'normal' ), array( 'dehydration' ), 'moist' ),
	'STIVES-BL-OATMEALSHEA-SOOTHING' => array( array( 'sensitive', 'dry' ), array( 'redness', 'dehydration' ), 'moist' ),
	'STIVES-BL-COLLAGENELASTIN-RENEWING' => array( array( 'normal', 'dry' ), array( 'aging' ), 'moist' ),
	'STIVES-SCRUB-BHA1-GREENTEA-BAMBOO' => array( array( 'oily', 'combination' ), array( 'acne', 'dullness' ), 'scrub' ),
	'STIVES-SCRUB-BHA2-APRICOT' => array( array( 'oily', 'combination' ), array( 'acne', 'dullness' ), 'scrub' ),
	'STIVES-SCRUB-TEATREEAPRICOT-ACNE' => array( array( 'oily', 'combination' ), array( 'acne', 'dullness' ), 'scrub' ),
	'STIVES-SCRUB-FRESHSKIN-APRICOT' => array( array( 'normal', 'combination' ), array( 'dullness' ), 'scrub' ),
	'STIVES-MC-COLLAGENELASTIN-RENEWING-90' => array( array( 'normal', 'dry' ), array( 'aging', 'dehydration' ), 'moist' ),
	'STIVES-FFW-CHAMOMILE-CALMSOOTHE' => array( array( 'sensitive' ), array( 'redness' ), 'foam' ),
	'STIVES-MC-ARGANGRAPE-NOURISHING-621' => array( array( 'dry', 'normal' ), array( 'dehydration' ), 'moist' ),
	'STIVES-FFW-APRICOT-GLOWMOIST' => array( array( 'normal', 'dry', 'combination' ), array( 'dehydration', 'dullness' ), 'foam' ),
	'STIVES-MC-ALOEVERA-LIGHTENING-621' => array( array( 'normal', 'dry' ), array( 'dehydration', 'dullness' ), 'moist' ),
	'STIVES-BW-SEASALTKELP-EXFOLIATING' => array( array( 'normal', 'oily' ), array( 'dullness' ), 'scrub' ),
	'STIVES-MC-COLLAGENELASTIN-RENEWING-170' => array( array( 'normal', 'dry' ), array( 'aging' ), 'moist' ),
	'STIVES-FC-TEATREE-ACNE' => array( array( 'oily' ), array( 'acne' ), 'face_wash' ),
	'STIVES-FC-COLLAGEN-RENEWING' => array( array( 'normal', 'dry' ), array( 'aging' ), 'face_wash' ),
	'STIVES-FC-ARGANCUC-HYDRATING' => array( array( 'dry' ), array( 'dehydration' ), 'face_wash' ),
	'STIVES-FC-ALOEHYAL-LIGHTENING' => array( array( 'sensitive' ), array( 'redness', 'dehydration' ), 'face_wash' ),
	'STIVES-MW-TEATREE-ACNE' => array( array( 'normal', 'dry', 'combination' ), array( 'aging' ), 'micellar' ),
	'STIVES-MW-COLLAGEN-RENEWING' => array( array( 'normal' ), array( 'aging' ), 'micellar' ),
	'STIVES-MW-ARGANCUC-HYDRATING' => array( array( 'dry' ), array( 'dehydration' ), 'micellar' ),
	'STIVES-MW-ALOEHYAL-LIGHTENING' => array( array( 'sensitive' ), array( 'redness', 'dehydration' ), 'micellar' ),
	'STIVES-ET-BHA-2SALICYLIC' => array( array( 'oily' ), array( 'acne' ), 'toner' ),
	'STIVES-ET-AHA-5LACTIC-ROSE' => array( array( 'dry', 'normal' ), array( 'dullness', 'dark_spots' ), 'toner' ),
	'STIVES-ET-AHA-5GLYCOLIC-APRICOT' => array( array( 'normal', 'combination' ), array( 'dullness', 'dark_spots' ), 'toner' ),
	'STIVES-FFW-TEATREE-ACNE' => array( array( 'oily' ), array( 'acne' ), 'foam' ),
	'STIVES-FFW-WATERMELON-HYDRATEGLOW' => array( array( 'dry' ), array( 'dehydration' ), 'foam' ),
	'STIVES-HS-ARGANMACADAMIACOND-BOOSTER' => array( array( 'straight', 'wavy', 'curly' ), array( 'frizz', 'damage' ), 'leave_in' ),
	'STIVES-HL-ARGANMACADAMIALEAVEIN-BOOSTER' => array( array( 'wavy', 'curly', 'straight' ), array( 'frizz', 'dryness' ), 'leave_in' ),
	'STIVES-HO-ARGANOIL-100' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'dryness', 'damage' ), 'oil' ),
	'STIVES-HM-CURLREVIVE-SOFTSHINY' => array( array( 'curly', 'coily', 'wavy' ), array( 'dryness', 'frizz' ), 'shampoo' ),
	'STIVES-HM-BOTOXMASK-NOURISHING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'mask' ),
	'STIVES-HM-BOTOXSHAMPOO-NOURISHING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'shampoo' ),
	'STIVES-HM-COLLAGENKERATINMASK-REPAIRING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'mask' ),
	'STIVES-HM-COLLAGENKERATINSHAMPOO-REPAIRING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'shampoo' ),
	'STIVES-HM-ARGANMACADAMIA-BOOSTER' => array( array( 'straight', 'wavy', 'curly' ), array( 'damage', 'dryness' ), 'mask' ),
	'STIVES-HM-ARGANMACADAMIASHAMPOO-BOOSTER' => array( array( 'straight', 'wavy', 'curly' ), array( 'dryness', 'damage' ), 'shampoo' ),
	'STIVES-HP-CURLCOMPLEX-BOUNCY' => array( array( 'curly', 'coily', 'wavy' ), array( 'frizz', 'dryness' ), 'leave_in' ),
	'STIVES-HL-CURLREVITALIZE-ELASTIC' => array( array( 'curly', 'coily', 'wavy' ), array( 'frizz', 'dryness' ), 'leave_in' ),
	'STIVES-HS-BOTOXCONDITIONING-NOURISHING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'leave_in' ),
	'STIVES-HL-BOTOXLEAVEIN-NOURISHING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'dryness' ), 'leave_in' ),
	'STIVES-HS-COLLAGENKERATINCOND-REPAIRING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'leave_in' ),
	'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING' => array( array( 'straight', 'wavy', 'curly', 'coily' ), array( 'damage', 'frizz' ), 'leave_in' ),
);

$changes = 0;
foreach ( $map as $sku => $row ) {
	$id = wc_get_product_id_by_sku( $sku );
	if ( ! $id || 'product' !== get_post_type( $id ) ) {
		echo "SKIP $sku: no product\n";
		continue;
	}
	list( $types, $concerns, $key ) = $row;
	$text = $usage[ $key ];
	printf( "%d | %s | types=%s | concerns=%s\n   %s\n", $id, $sku, implode( ',', $types ), implode( ',', $concerns ), $text );
	if ( $write ) {
		update_post_meta( $id, '_needs_types', $types );
		update_post_meta( $id, '_needs_concerns', $concerns );
		update_post_meta( $id, '_needs_usage', $text );
		$changes++;
	}
}
echo $write ? "WROTE $changes products\n" : "DRY RUN: nothing written (" . count( $map ) . " products previewed)\n";
