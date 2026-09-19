<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Log;
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
					<div class="bg-error-50 text-center md:p-8 p-6">
						<div class="flex justify-center mb-4">
							<div class="size-32 relative flex items-center justify-center bg-error-100 rounded-full shadow-[0_4px_6px_-4px_#0000001A]">
								<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/vpn.svg">

								<div class="size-11 absolute right-0 bottom-0 flex items-center justify-center bg-[#EA3A3D] rounded-full border-4 border-white shadow-[0_4px_6px_-4px_#0000001A]">
									<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/white-link.svg">
								</div>
							</div>
						</div>
						<div class="sm:text-2xl text-xl text-[#1D293D] font-bold mb-4">
							فیلترشکن را خاموش کنید!
						</div>
						<div class="text-[#62748E] text-sm">
							امکان پرداخت با آی‌پی غیرایرانی وجود ندارد.
						</div>
					</div>
					<div class="bg-white md:p-8 p-6">
						<div class="border border-[#F1F5F9] bg-[#F8FAFC] rounded-2xl space-y-4 p-4 mb-8">
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">مبلغ تراکنش</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( CurrenciesEnum::tryFrom( $transaction->currency )->price( $transaction->amount ) ) ); ?></div>
							</div>
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">شماره تراکنش</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( $transaction->id ) ); ?></div>
							</div>
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">تاریخ و زمان</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( verta( $transaction->created_at )->format( 'Y/m/d - H:i' ) ) ); ?></div>
							</div>
							<div class="flex flex-wrap gap-2 text-sm text-nowrap">
								<div class="text-[#62748E]">آی.پی شما</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( Helper::get_real_ip() ) ); ?></div>
							</div>
						</div>
						<div class="text-center mb-6">
							<a href=""
							   class="flex gap-2 items-center justify-center ms:text-base text-sm bg-primary-600 hover:bg-primary-700 rounded-xl text-white font-semibold py-3 px-5">
								<img class="h-5" src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/refresh-white.svg">
								بررسی مجدد و تلاش دوباره
							</a>
						</div>
						<div class="font-roboto text-xs text-center text-[#90A1B9]">
							RayID: <?php echo Helper::en_num( Log::$ray_id ); ?>
						</div>
					</div>
				</div>
			</div>
			<div class="text-center mt-8">
				<a href="<?php echo esc_url( site_url() ); ?>" class="text-gray-600 font-semibold inline-flex gap-2 items-center">
					<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/arrow-right-gray.svg">
					رفتن به فروشگاه
				</a>
			</div>
		</div>
	</section>
</section>

</body>
</html>
