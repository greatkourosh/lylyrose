<?php

use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

/** @var string $error */
/** @var Transaction $transaction */

?>
<!doctype html>
<html dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title><?php bloginfo( 'name' ); ?> - خطا</title>

	<link rel="stylesheet" href="<?php echo esc_url( GATELAND_URL ) . '/assets/css/style.css' ?>">

</head>
<body>

<section class="gateland-container text-base">

	<section class="bg-[#F9FAFB] text-base min-h-screen flex flex-col items-center justify-center p-4">
		<div class="container my-auto">
			<div class="flex justify-center">
				<div class="bg-white shadow-[0_20px_25px_-5px_#0000001A] rounded-3xl overflow-hidden max-w-full w-[446px]">
					<div class="bg-gray-200 text-center md:p-8 p-6">
						<div class="flex justify-center mb-4">
							<div class="size-32 shadow-[0_10px_15px_-3px_#0000001A] rounded-full">
								<div class="size-32 relative flex items-center justify-center bg-[#E2E8F0]  rounded-full shadow-[0_4px_6px_-4px_#0000001A]">
									<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/warning-gray.svg">

									<div class="size-11 absolute right-0 bottom-0 flex items-center justify-center bg-[#62748E] rounded-full border-4 border-white shadow-[0_4px_6px_-4px_#0000001A]">
										<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/white-link.svg">
									</div>
								</div>
							</div>
						</div>
						<div class="sm:text-2xl text-xl text-[#1D293D] font-bold mb-4">
							خطا :)
						</div>
						<div class="text-[#62748E] text-sm">
							<?php echo esc_html( $error ); ?>
						</div>
					</div>
					<div class="bg-white md:p-8 p-6">
						<div class="text-xs text-center text-[#90A1B9]">
							اگر نیاز به کمک یا سوالی دارید، با پشتیبانی تماس بگیرید.
						</div>
					</div>
				</div>
			</div>

			<div class="text-center mt-8">
				<a href="<?php echo esc_url( site_url() ); ?>"
				   class="text-gray-600 font-semibold inline-flex gap-2 items-center">
					<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/arrow-right-gray.svg">
					رفتن به فروشگاه
				</a>
			</div>
		</div>
	</section>

</section>

</body>
</html>