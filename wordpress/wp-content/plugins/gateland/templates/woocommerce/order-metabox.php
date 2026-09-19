<?php

use Nabik\Gateland\Enums\Transaction\CurrenciesEnum;
use Nabik\Gateland\Enums\Transaction\StatusesEnum;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Transaction;

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'custom-style', GATELAND_URL . 'assets/css/style.css', [], GATELAND_VERSION );

?>
<section class="gateland-container">
	<div class="bg-white border border-gray-200 rounded-xl overflow-hidden my-2">
		<div class="overflow-auto custom-scrollbar">
			<table class="w-full">
				<thead class="text-xs text-gray-600 text-nowrap border-b border-gray-200">
				<tr>
					<td class="bg-gray-50 py-3 px-5">
						شماره تراکنش
					</td>
					<td class="bg-gray-50 py-3 px-5">
						تاریخ ایجاد
					</td>
					<td class="bg-gray-50 py-3 px-5">
						مبلغ
					</td>
					<td class="bg-gray-50 py-3 px-5">
						درگاه
					</td>
					<td class="bg-gray-50 py-3 px-5">
						وضعیت
					</td>
					<td class="bg-gray-50 text-center py-3 px-5">
						جزئیات
					</td>
				</tr>
				</thead>

				<tbody class="w-full text-sm text-gray-600">
				<?php
				/** @var Transaction[] $transactions */
				foreach ( $transactions as $transaction ): ?>
					<tr>
						<td class="py-4 md:px-5 px-3">
							<?php echo esc_html( Helper::fa_num( $transaction->id ) ); ?>
						</td>
						<td class="py-4 md:px-5 px-3">
							<?php echo esc_html( Helper::fa_num( Helper::date( $transaction->created_at, 'Y/m/d H:i' ) ) ); ?>
						</td>
						<td class="py-4 md:px-5 px-3">
							<?php echo esc_html( Helper::fa_num( CurrenciesEnum::tryFrom( $transaction->currency )->price( $transaction->amount ) ) ); ?>
						</td>
						<td class="py-4 md:px-5 px-3">
							<?php echo esc_html( $transaction->gateway_label ); ?>
						</td>
						<td class="py-4 md:px-5 px-3">
							<div class="flex gap-4">
								<?php if ( $transaction->status == StatusesEnum::STATUS_PAID ) { ?>
									<div class="inline-block rounded-full bg-success-50 text-xs text-nowrap text-success-700 px-2 py-1">
										پرداخت شده
									</div>
								<?php } elseif ( $transaction->status == StatusesEnum::STATUS_FAILED ) { ?>
									<div class="inline-block rounded-full bg-error-50 text-xs text-nowrap text-error-700 px-2 py-1">
										ناموفق
									</div>
								<?php } elseif ( $transaction->status == StatusesEnum::STATUS_PENDING ) { ?>
									<div class="inline-block rounded-full bg-warning-50 text-xs text-nowrap text-warning-700 px-2 py-1">
										در انتظار پرداخت
									</div>
								<?php } elseif ( $transaction->status == StatusesEnum::STATUS_REFUND ) { ?>
									<div class="inline-block rounded-full bg-gray-100 text-xs text-nowrap text-gray-700 px-2 py-1">
										استرداد شده
									</div>
								<?php } ?>
							</div>
						</td>
						<td class="text-center py-4 md:px-5 px-3">
							<a href="<?php echo esc_url( $transaction->getReceiptURL() ); ?>" target="_blank">
								<img src="<?php echo GATELAND_URL . 'assets'; ?>/images/icons/eye.svg"/>
							</a>
						</td>
					</tr>
				<?php
				endforeach;
				?>
				</tbody>
			</table>
		</div>
	</div>
</section>