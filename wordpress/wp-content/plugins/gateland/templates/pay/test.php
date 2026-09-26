<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

/** @var Transaction $transaction */

$success_url = add_query_arg( [
	'is_paid' => 1,
], $transaction->gateway_callback );

$fail_url = add_query_arg( [
	'is_paid' => 0,
], $transaction->gateway_callback );

?>
<!doctype html>
<html dir="rtl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title><?php bloginfo( 'name' ); ?> - پرداخت</title>

	<link rel="stylesheet" href="<?php echo esc_url( GATELAND_URL ) . '/assets/css/style.css' ?>">

</head>
<body>

<section class="gateland-container text-base">

	<section class="bg-[#F9FAFB] text-base min-h-screen flex flex-col items-center justify-center p-4">
		<div class="container my-auto">
			<div class="flex justify-center">
				<div class="bg-white shadow-[0_20px_25px_-5px_#0000001A] rounded-3xl overflow-hidden max-w-full w-[446px]">
					<div class="bg-blue-50 text-center md:p-8 p-6">
						<div class="flex justify-center mb-4">
							<div class="size-32 relative flex items-center justify-center bg-blue-100 rounded-full shadow-[0_4px_6px_-4px_#0000001A]">
								<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/command.svg">

								<div class="size-11 absolute right-0 bottom-0 flex items-center justify-center bg-blue-700 rounded-full border-4 border-white shadow-[0_4px_6px_-4px_#0000001A]">
									<img src="<?php echo esc_url( GATELAND_URL ); ?>/assets/images/icons/white-link.svg">
								</div>
							</div>
						</div>
						<div class="sm:text-2xl text-xl text-[#1D293D] font-bold mb-4">
							درگاه آزمایشی توسعه‌دهندگان
						</div>
						<div class="flex justify-center text-sm">
							<div class="flex items-center gap-1.5 text-blue-700 font-medium border border-blue-600 rounded-full py-0.5 px-2">
								<div class="size-1.5 bg-blue-500 rounded-full"></div>
								محیط شبیه‌سازی (Sandbox)
							</div>
						</div>
					</div>
					<div class="bg-white md:p-8 p-6">
						<div class="border border-[#F1F5F9] bg-[#F8FAFC] rounded-2xl space-y-4 p-4 mb-8">
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">پذیرنده</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo $transaction->client_label; ?></div>
							</div>
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">آی.پی شما</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( $transaction->ip ) ); ?></div>
							</div>
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">مبلغ تراکنش</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( CurrenciesEnum::tryFrom( $transaction->currency )->price( $transaction->amount ) ) ); ?></div>
							</div>
							<div class="flex flex-wrap gap-2 border-b border-[#E2E8F0] text-sm text-nowrap pb-2">
								<div class="text-[#62748E]">تاریخ و زمان</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( verta( $transaction->created_at )->format( 'Y/m/d - H:i' ) ) ); ?></div>
							</div>
							<div class="flex flex-wrap gap-2 text-sm text-nowrap">
								<div class="text-[#62748E]">شماره تراکنش</div>
								<div class="text-[#314158] font-medium mr-auto"><?php echo esc_html( Helper::fa_num( $transaction->id ) ); ?></div>
							</div>
						</div>
						<div class="text-center mb-6">
							<a href="<?php echo esc_url( $success_url ); ?>"
							   class="block sm:text-base text-sm bg-success-500 hover:bg-success-600 rounded-xl text-white font-semibold py-3 px-5 mb-3">
								شبیه‌سازی پرداخت موفق
							</a>
							<a href="<?php echo esc_url( $fail_url ); ?>"
							   class="block sm:text-base text-sm bg-error-400 hover:bg-error-500 rounded-xl text-white font-semibold py-3 px-5">
								شبیه‌سازی پرداخت ناموفق
							</a>
						</div>
						<div class="text-gray-500 text-center text-sm">
							این صفحه صرفاً جهت تست فرآیند پرداخت برای توسعه‌دهندگان می‌باشد و تراکنش مالی واقعی انجام
							نمی‌شود.
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

</section>

</body>
</html>